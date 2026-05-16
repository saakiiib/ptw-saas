<?php

namespace App\Http\Controllers;

use App\Mail\BirthdayVoucherMail;
use App\Mail\SubscriptionReminderMail;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\DeliverySubscription;
use App\Models\GiftCard;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusLog;
use App\Models\User;
use App\Models\UserPoint;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    public function dashboard()
    {
        if (Auth::check()) {
            $user = auth()->user();

            if ($user->user_type == '1') {
                return redirect()->route('admin.dashboard');
            } else {
                return redirect()->route('user.dashboard');
            }
        } else {
            return redirect()->route('login');
        }
    }

    public function saveFcmToken(Request $request)
    {
        $request->validate([
            'token' => 'required|string'
        ]);

        auth()->user()->update([
            'fcm_token' => $request->token
        ]);

        return response()->json(['success' => true]);
    }

    public function orderPopupData($id)
    {
        $order = Order::with(['items.options'])->findOrFail($id);
        return response()->json([
            'id'             => $order->id,
            'order_number'   => $order->order_number,
            'status'         => $order->status,
            'delivery_type'  => $order->delivery_type,
            'time'           => $order->time,
            'first_name'     => $order->first_name,
            'last_name'      => $order->last_name,
            'email'          => $order->email,
            'phone'          => $order->phone,
            'address_1'      => $order->address_1,
            'address_2'      => $order->address_2,
            'city'           => $order->city,
            'postcode'       => $order->postcode,
            'total'          => $order->total,
            'payment_method' => $order->payment_method,
            'notes'          => $order->notes,
            'items'          => $order->items->map(function($item) {
                return [
                    'product_name' => $item->product_name,
                    'quantity'     => $item->quantity,
                    'total'        => $item->total,
                    'options'      => $item->options->map(fn($o) => [
                        'option_name' => $o->option_name
                    ])
                ];
            })
        ]);
    }

    public function getPendingOrdersQueue()
    {
        $orders = Order::with(['items.options'])
            ->whereNotIn('status', ['delivered', 'delivery_failed', 'rejected'])
            ->orderByRaw("FIELD(status, 'new', 'accepted', 'preparing', 'ready')")
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function($order) {
                return [
                    'id'             => $order->id,
                    'order_number'   => $order->order_number,
                    'status'         => $order->status,
                    'delivery_type'  => $order->delivery_type,
                    'time'           => $order->time,
                    'first_name'     => $order->first_name,
                    'last_name'      => $order->last_name,
                    'email'          => $order->email,
                    'phone'          => $order->phone,
                    'address_1'      => $order->address_1,
                    'address_2'      => $order->address_2,
                    'city'           => $order->city,
                    'postcode'       => $order->postcode,
                    'total'          => $order->total,
                    'payment_method' => $order->payment_method,
                    'notes'          => $order->notes,
                    'items'          => $order->items->map(fn($item) => [
                        'product_name' => $item->product_name,
                        'quantity'     => $item->quantity,
                        'total'        => $item->total,
                        'options'      => $item->options->map(fn($o) => ['option_name' => $o->option_name])
                    ])
                ];
            });

        return response()->json(['orders' => $orders]);
    }

    public function updateOrderStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:accepted,preparing,ready,delivered,rejected,delivery_failed'
        ]);

        $order = Order::findOrFail($id);
        $oldStatus = $order->status;

        $order->update(['status' => $request->status]);

        OrderStatusLog::create([
            'order_id'   => $order->id,
            'old_status' => $oldStatus,
            'new_status' => $request->status,
            'changed_by' => auth()->id(),
            'metadata'   => json_encode(['source' => 'admin_popup'])
        ]);

        if (in_array($request->status, ['rejected', 'delivery_failed'])) {
            $this->reverseOrderResources($order);
        }

        return response()->json(['success' => true]);
    }

    private function reverseOrderResources($order)
    {
        if ($order->gift_card_id) {
            $giftCard = GiftCard::find($order->gift_card_id);
            if ($giftCard) {
                $giftCard->update([
                    'balance' => $giftCard->balance + $order->gift_card_discount,
                    'status' => 'new',
                    'redeemed_by' => null,
                    'redeemed_at' => null,
                    'order_id' => null
                ]);
            }
        }

        if ($order->coupon_id && $order->user_id) {
            $coupon = Coupon::find($order->coupon_id);
            if ($coupon) {
                $coupon->update([
                    'used_count' => max(0, $coupon->used_count - 1)
                ]);

                if ($coupon->is_birthday_voucher) {
                    $coupon->users()->updateExistingPivot($order->user_id, [
                        'used_count' => 0
                    ]);
                } else {
                    CouponUsage::where([
                        'coupon_id' => $coupon->id,
                        'user_id' => $order->user_id
                    ])->decrement('usage_count');
                }
            }
        }

        if ($order->user_id) {
            if ($order->points_used > 0) {
                UserPoint::where('order_id', $order->id)
                    ->where('point', -($order->points_used * 100))
                    ->delete();
            }

            UserPoint::where('order_id', $order->id)
                ->where('point', '>', 0)
                ->delete();
        }
    }

    public function printOrder($id)
    {
        $order = Order::with(['items.options'])->findOrFail($id);
        return view('admin.orders.print', compact('order'));
    }

    public function adminHome(Request $request)
    {
        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : now()->startOfMonth();
        $endDate = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : now();

        $periodDays = $startDate->diffInDays($endDate);
        $prevStartDate = $startDate->copy()->subDays($periodDays);
        $prevEndDate = $startDate->copy()->subDay();

        $upcomingBirthdays = User::where('user_type', 2)
            ->whereNotNull('dob')
            ->get()
            ->filter(function ($user) {
                $daysUntil = $user->days_until_birthday;
                return $daysUntil !== null && $daysUntil >= 0 && $daysUntil <= 7;
            })
            ->sortBy('days_until_birthday')
            ->values();

        $this->sendBirthdayVouchers();
        $this->sendSubscriptionReminderEmails();

        $totalOrders = Order::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'delivered')->count();

        $totalSales = Order::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'delivered')->sum('total');

        $avgOrderValue = $totalOrders > 0 ? $totalSales / $totalOrders : 0;

        $newCustomers = User::where('user_type', 2)
            ->whereBetween('created_at', [$startDate, $endDate])->count();

        $repeatedCustomers = User::where('user_type', 2)
            ->whereRaw("(SELECT COUNT(*) FROM orders WHERE orders.user_id = users.id AND orders.status = 'delivered' AND orders.created_at BETWEEN ? AND ?) > 1", [$startDate, $endDate])
            ->count();

        $pendingOrders = Order::whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('status', ['pending', 'confirmed', 'preparing', 'ready', 'out_for_delivery'])
            ->count();

        $prevTotalOrders = Order::whereBetween('created_at', [$prevStartDate, $prevEndDate])
            ->where('status', 'delivered')->count();

        $prevTotalSales = Order::whereBetween('created_at', [$prevStartDate, $prevEndDate])
            ->where('status', 'delivered')->sum('total');

        $prevAvgOrder = $prevTotalOrders > 0 ? $prevTotalSales / $prevTotalOrders : 0;

        $prevNewCustomers = User::where('user_type', 2)
            ->whereBetween('created_at', [$prevStartDate, $prevEndDate])->count();

        $prevRepeatedCustomers = User::where('user_type', 2)
            ->whereRaw("(SELECT COUNT(*) FROM orders WHERE orders.user_id = users.id AND orders.status = 'delivered' AND orders.created_at BETWEEN ? AND ?) > 1", [$prevStartDate, $prevEndDate])
            ->count();

        $prevPendingOrders = Order::whereBetween('created_at', [$prevStartDate, $prevEndDate])
            ->whereIn('status', ['pending', 'confirmed', 'preparing', 'ready', 'out_for_delivery'])
            ->count();

        $revenuePercent = $prevTotalSales > 0 ? (($totalSales - $prevTotalSales) / $prevTotalSales) * 100 : 0;
        $ordersPercent = $prevTotalOrders > 0 ? (($totalOrders - $prevTotalOrders) / $prevTotalOrders) * 100 : 0;
        $avgOrderPercent = $prevAvgOrder > 0 ? (($avgOrderValue - $prevAvgOrder) / $prevAvgOrder) * 100 : 0;
        $newCustomersPercent = $prevNewCustomers > 0 ? (($newCustomers - $prevNewCustomers) / $prevNewCustomers) * 100 : 0;
        $repeatedPercent = $prevRepeatedCustomers > 0 ? (($repeatedCustomers - $prevRepeatedCustomers) / $prevRepeatedCustomers) * 100 : 0;
        $pendingPercent = $prevPendingOrders > 0 ? (($pendingOrders - $prevPendingOrders) / $prevPendingOrders) * 100 : 0;

        $dailyRevenue = Order::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'delivered')
            ->selectRaw('DATE(created_at) as date, SUM(total) as revenue, COUNT(*) as orders')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $chartData = [];
        foreach ($dailyRevenue as $day) {
            $chartData[] = [
                'date' => Carbon::parse($day->date)->format('M d'),
                'revenue' => (float) $day->revenue,
                'orders' => $day->orders
            ];
        }

        $paymentMethods = Order::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'delivered')
            ->selectRaw('payment_method, COUNT(*) as count, SUM(total) as total')
            ->groupBy('payment_method')
            ->get();

        $paymentData = [];
        foreach ($paymentMethods as $method) {
            $paymentData[] = [
                'method' => ucfirst($method->payment_method),
                'count' => $method->count,
                'total' => (float) $method->total
            ];
        }

        $topProducts = OrderItem::whereHas('order', function($q) use ($startDate, $endDate) {
            $q->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'delivered');
        })
            ->selectRaw('product_name, SUM(quantity) as total_qty, SUM(total) as revenue')
            ->groupBy('product_name')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get();

        $recentOrders = Order::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'delivered')
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $peakHours = Order::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'delivered')
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count, SUM(total) as revenue')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        $peakData = [];
        foreach ($peakHours as $hour) {
            $peakData[] = [
                'hour' => str_pad($hour->hour, 2, '0', STR_PAD_LEFT) . ':00',
                'orders' => $hour->count,
                'revenue' => (float) $hour->revenue
            ];
        }

        return view('admin.pages.dashboard', [
            'upcomingBirthdays' => $upcomingBirthdays,
            'totalOrders' => $totalOrders,
            'totalSales' => $totalSales,
            'avgOrderValue' => $avgOrderValue,
            'newCustomers' => $newCustomers,
            'repeatedCustomers' => $repeatedCustomers,
            'pendingOrders' => $pendingOrders,
            'revenuePercent' => number_format($revenuePercent, 1),
            'ordersPercent' => number_format($ordersPercent, 1),
            'avgOrderPercent' => number_format($avgOrderPercent, 1),
            'newCustomersPercent' => number_format($newCustomersPercent, 1),
            'repeatedPercent' => number_format($repeatedPercent, 1),
            'pendingPercent' => number_format($pendingPercent, 1),
            'chartData' => json_encode($chartData),
            'paymentData' => json_encode($paymentData),
            'topProducts' => $topProducts,
            'recentOrders' => $recentOrders,
            'peakData' => json_encode($peakData),
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d')
        ]);
    }

    private function sendBirthdayVouchers()
    {
        $tomorrow = \Carbon\Carbon::tomorrow();

        $birthdayUsers = User::where('user_type', 2)
            ->whereNotNull('dob')
            ->get()
            ->filter(function ($user) use ($tomorrow) {
                $dob = \Carbon\Carbon::parse($user->dob);
                return $dob->month === $tomorrow->month && $dob->day === $tomorrow->day;
            });

        $birthdayVoucher = Coupon::where('is_birthday_voucher', true)
            ->where('is_active', true)
            ->first();

        if (!$birthdayVoucher || $birthdayUsers->isEmpty()) {
            return;
        }

        foreach ($birthdayUsers as $user) {
            $alreadySent = $user->coupons()
                ->where('coupon_id', $birthdayVoucher->id)
                ->wherePivot('sent_year', now()->year)
                ->first();

            if (!$alreadySent) {
                $user->coupons()->attach($birthdayVoucher->id, [
                    'sent_at' => now(),
                    'sent_year' => now()->year,
                    'used_count' => 0
                ]);

                Mail::to($user->email)->send(new BirthdayVoucherMail($user, $birthdayVoucher));
            }
        }
    }

    private function sendSubscriptionReminderEmails()
    {
        $sevenDaysFromNow = \Carbon\Carbon::now()->addDays(7)->startOfDay();

        $subscriptions = DeliverySubscription::where('status', 'active')
            ->whereDate('ends_at', $sevenDaysFromNow)
            ->where('sent_7_day_reminder', false)
            ->get();

        foreach ($subscriptions as $subscription) {
            $user = $subscription->user;

            if ($user && $user->email) {
                Mail::to($user->email)->send(new SubscriptionReminderMail($user, $subscription, 7));

                $subscription->update(['sent_7_day_reminder' => true]);
            }
        }

        $tomorrowStart = \Carbon\Carbon::tomorrow()->startOfDay();

        $subscriptions = DeliverySubscription::where('status', 'active')
            ->whereDate('ends_at', $tomorrowStart)
            ->where('sent_1_day_reminder', false)
            ->get();

        foreach ($subscriptions as $subscription) {
            $user = $subscription->user;

            if ($user && $user->email) {
                Mail::to($user->email)->send(new SubscriptionReminderMail($user, $subscription, 1));

                $subscription->update(['sent_1_day_reminder' => true]);
            }
        }
    }

    public function userHome()
    {
        return view('user.dashboard');
    }

    public function cleanDB()
    {
        $tables = [
            'orders',
            'order_items',
            'order_item_options',
            'order_status_logs'
        ];

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        foreach ($tables as $table) {
            DB::table($table)->truncate();
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        return "Cleaned successfully.";
    }
}
