<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Customer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Mail\SendCustomerOtp;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;

class CustomerApiController extends Controller
{

    public function auth(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $customer = Customer::where('email', $request->email)->first();

        if ($customer) {
            if (Hash::check($request->password, $customer->password)) {
                if ($customer->otp_verified) {
                    return response()->json([
                        'message' => 'Account already verified. Please login.',
                    ], 200);
                }
            } else {
                return response()->json(['message' => 'Email already exists. Please login or use a different email.'], 409);
            }
        } else {
            $customer = Customer::create([
                'email' => $request->email,
                'password' => bcrypt($request->password),
            ]);
        }

        if (!$customer->otp_verified) {
            $otp = rand(100000, 999999);
            $cacheKey = 'customer_otp_' . $customer->email;

            Cache::put($cacheKey, [
                'otp' => $otp,
                'customer_id' => $customer->cid,
                'created_at' => now()
            ], 600); // 10 minutes

            $customer->otp_verified = false;
            $customer->save();

            try {
                Mail::raw("Your OTP code is: {$otp}\n\nThis code will expire in 10 minutes.\n\nIf you didn't request this code, please ignore this email.", function ($message) use ($customer) {
                    $message->to($customer->email)
                        ->subject('Your OTP Code');
                });

                Log::info('OTP email sent successfully', [
                    'email' => $customer->email,
                    'otp' => $otp
                ]);

                return response()->json([
                    'message' => 'OTP sent to email',
                    'debug' => config('app.debug') ? ['otp' => $otp] : []
                ], 200);
            } catch (\Exception $e) {
                Log::error('Failed to send OTP email', [
                    'email' => $customer->email,
                    'error' => $e->getMessage()
                ]);

                return response()->json([
                    'message' => 'Failed to send OTP email',
                    'error' => config('app.debug') ? $e->getMessage() : 'Email service unavailable'
                ], 500);
            }
        }
    }

    public function verify(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp' => 'required|digits:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $customer = Customer::where('email', $request->email)->first();
        if (!$customer) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        $cacheKey = 'customer_otp_' . $request->email;
        $cachedData = Cache::get($cacheKey);

        if (!$cachedData || $cachedData['otp'] != $request->otp || $cachedData['customer_id'] != $customer->cid) {
            return response()->json(['message' => 'Invalid or expired OTP'], 400);
        }

        if (now()->diffInMinutes($cachedData['created_at']) > 10) {
            Cache::forget($cacheKey);
            return response()->json(['message' => 'OTP has expired'], 400);
        }

        $customer->otp_verified = true;
        $customer->save();

        Cache::forget($cacheKey);

        return response()->json(['message' => 'OTP verified successfully'], 200);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $customer = Customer::where('email', $request->email)->first();
        if (
            !$customer ||
            !Hash::check($request->password, $customer->password)
        ) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if (!$customer->otp_verified) {
            return response()->json(['message' => 'Please verify your email (OTP) before logging in.'], 403);
        }

        return response()->json([
            'message' => 'Login successful',
            'data' => $this->formatCustomer($customer)
        ], 200);
    }

    public function setup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:customers,email',
            'full_name' => 'required|string|max:255',
            'gender' => 'required|in:male,female,other',
            'phone' => 'required|string|max:20',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $customer = Customer::where('email', $request->email)->first();
        if (!$customer) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        if (!$customer->otp_verified) {
            return response()->json(['message' => 'Please verify your email (OTP) before setting up your profile.'], 403);
        }

        $customer->full_name = $request->full_name;
        $customer->gender = $request->gender;
        $customer->phone = $request->phone;

        if ($request->hasFile('photo')) {
            if ($customer->photo) {
                $oldPath = str_replace('/storage/', '', $customer->photo);
                Storage::disk('public')->delete($oldPath);
            }

            $photo = $request->file('photo');
            $photoName = time() . '.' . $photo->getClientOriginalExtension();
            $path = $photo->storeAs('customers', $photoName, 'public');
            $customer->photo = '/storage/' . $path;
        }

        $customer->save();

        return response()->json([
            'message' => 'Account setup successful',
            'data' => $this->formatCustomer($customer)
        ], 200);
    }

    public function show(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $customer = Customer::where('email', $request->email)->first();
        if (!$customer) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        return response()->json([
            'data' => $this->formatCustomer($customer)
        ], 200);
    }

    public function updatePhoto(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:customers,email',
            'photo' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $customer = Customer::where('email', $request->email)->first();

        if ($customer->photo) {
            $oldPath = str_replace('/storage/', '', $customer->photo);
            Storage::disk('public')->delete($oldPath);
        }

        $photo = $request->file('photo');
        $photoName = time() . '.' . $photo->getClientOriginalExtension();
        $path = $photo->storeAs('customers', $photoName, 'public');
        $customer->photo = '/storage/' . $path;
        $customer->save();

        return response()->json([
            'message' => 'Profile photo updated',
            'data' => $this->formatCustomer($customer)
        ], 200);
    }

    public function requestResetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:customers,email',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $customer = Customer::where('email', $request->email)->first();
        if (!$customer) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        $otp = rand(100000, 999999);
        $cacheKey = 'customer_reset_otp_' . $customer->email;
        Cache::put($cacheKey, [
            'otp' => $otp,
            'customer_id' => $customer->cid,
            'created_at' => now()
        ], 600);

        $customer->otp_verified = false;
        $customer->save();

        Mail::raw("Your password reset OTP code is: {$otp}\n\nThis code will expire in 10 minutes.", function ($message) use ($customer) {
            $message->to($customer->email)
                ->subject('Password Reset OTP');
        });

        return response()->json(['message' => 'OTP sent to email'], 200);
    }

    public function resendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:customers,email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $customer = Customer::where('email', $request->email)->first();

        // Only resend if not verified
        if ($customer->otp_verified) {
            return response()->json(['message' => 'Account already verified. Please login.'], 200);
        }

        $otp = rand(100000, 999999);
        $cacheKey = 'customer_otp_' . $customer->email;

        Cache::put($cacheKey, [
            'otp' => $otp,
            'customer_id' => $customer->cid,
            'created_at' => now()
        ], 600); // 10 minutes

        try {
            Mail::raw("Your OTP code is: {$otp}\n\nThis code will expire in 10 minutes.\n\nIf you didn't request this code, please ignore this email.", function ($message) use ($customer) {
                $message->to($customer->email)
                    ->subject('Your OTP Code');
            });

            return response()->json([
                'message' => 'OTP resent to email',
                'debug' => config('app.debug') ? ['otp' => $otp] : []
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to resend OTP email',
                'error' => config('app.debug') ? $e->getMessage() : 'Email service unavailable'
            ], 500);
        }
    }

    public function verifyResetOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:customers,email',
            'otp' => 'required|digits:6',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $customer = Customer::where('email', $request->email)->first();
        $cacheKey = 'customer_reset_otp_' . $customer->email;
        $cachedData = Cache::get($cacheKey);

        if (!$cachedData || $cachedData['otp'] != $request->otp || $cachedData['customer_id'] != $customer->cid) {
            return response()->json(['message' => 'Invalid or expired OTP'], 400);
        }

        if (now()->diffInMinutes($cachedData['created_at']) > 10) {
            Cache::forget($cacheKey);
            return response()->json(['message' => 'OTP has expired'], 400);
        }

        $customer->otp_verified = true;
        $customer->save();
        Cache::forget($cacheKey);

        return response()->json(['message' => 'OTP verified successfully'], 200);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:customers,email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $customer = Customer::where('email', $request->email)->first();

        // Only allow if OTP is verified
        if (!$customer || !$customer->otp_verified) {
            return response()->json(['message' => 'Please verify your email (OTP) before resetting your password.'], 403);
        }

        $customer->password = bcrypt($request->password);
        // $customer->otp_verified = false; // Remove this line
        $customer->save();

        return response()->json(['message' => 'Password reset successful'], 200);
    }

    protected function formatCustomer($customer)
    {
        return [
            'cid' => $customer->cid,
            'full_name' => $customer->full_name,
            'gender' => $customer->gender,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'photo' => $customer->photo ? url($customer->photo) : null,
        ];
    }
}
