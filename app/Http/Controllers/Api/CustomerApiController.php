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

        if (!$customer) {
            // Check again for duplicate email before creating
            if (Customer::where('email', $request->email)->exists()) {
                return response()->json(['message' => 'Email already exists'], 409);
            }

            // Create new customer
            $customer = Customer::create([
                'email' => $request->email,
                'password' => bcrypt($request->password),
            ]);
        } else {
            // Verify existing customer's password
            if (!Hash::check($request->password, $customer->password)) {
                return response()->json(['message' => 'Invalid credentials'], 401);
            }
        }

        // Generate OTP and store in cache with expiration
        $otp = rand(100000, 999999);
        $cacheKey = 'customer_otp_' . $customer->email;

        // Store OTP in cache for 10 minutes
        Cache::put($cacheKey, [
            'otp' => $otp,
            'customer_id' => $customer->cid,
            'created_at' => now()
        ], 600); // 10 minutes

        // Reset OTP verification status
        $customer->otp_verified = false;
        $customer->save();

        // Send OTP via email with error handling
        try {
            // Send email with inline content
            Mail::raw("Your OTP code is: {$otp}\n\nThis code will expire in 10 minutes.\n\nIf you didn't request this code, please ignore this email.", function ($message) use ($customer) {
                $message->to($customer->email)
                        ->subject('Your OTP Code');
            });

            // Log successful email attempt
            Log::info('OTP email sent successfully', [
                'email' => $customer->email,
                'otp' => $otp // Remove this in productionar
            ]);

            return response()->json([
                'message' => 'OTP sent to email',
                'debug' => config('app.debug') ? ['otp' => $otp] : [] // Only show in debug mode
            ], 200);

        } catch (\Exception $e) {
            // Log email sending error
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

    // 2. Verify OTP
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

        // Check if OTP exists and is valid
        if (!$cachedData || $cachedData['otp'] != $request->otp || $cachedData['customer_id'] != $customer->cid) {
            return response()->json(['message' => 'Invalid or expired OTP'], 400);
        }

        // Check if OTP is expired (additional safety check)
        if (now()->diffInMinutes($cachedData['created_at']) > 10) {
            Cache::forget($cacheKey);
            return response()->json(['message' => 'OTP has expired'], 400);
        }

        // Mark as verified and remove OTP from cache
        $customer->otp_verified = true;
        $customer->save();

        Cache::forget($cacheKey);

        return response()->json(['message' => 'OTP verified successfully'], 200);
    }

    // Test email configuration
    public function testEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $testOtp = 123456;
            Mail::to($request->email)->send(new SendCustomerOtp($testOtp));

            return response()->json([
                'message' => 'Test email sent successfully',
                'email' => $request->email
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to send test email',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // 3. Setup/fill customer info (after OTP verified)
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

        // Check if OTP is verified
        if (!$customer->otp_verified) {
            return response()->json(['message' => 'Please verify OTP first'], 403);
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

    // Show customer profile by email
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
        if (!$customer || !Hash::check($request->password, $customer->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        return response()->json([
            'message' => 'Login successful',
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
        $otp = rand(100000, 999999);
        $customer->otp = $otp;
        $customer->otp_verified = false;
        $customer->save();

        // Send OTP via email
        Mail::to($customer->email)->send(new SendCustomerOtp($otp));

        return response()->json(['message' => 'OTP sent to email'], 200);
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
        if (!$customer || $customer->otp != $request->otp) {
            return response()->json(['message' => 'Invalid OTP'], 400);
        }

        $customer->otp_verified = true;
        $customer->save();

        return response()->json(['message' => 'OTP verified, you can now reset your password'], 200);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:customers,email',
            'password' => 'required|string|min:6|confirmed', // expects password_confirmation field
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $customer = Customer::where('email', $request->email)->first();
        if (!$customer || !$customer->otp_verified) {
            return response()->json(['message' => 'OTP not verified'], 403);
        }

        $customer->password = bcrypt($request->password);
        $customer->otp_verified = false; // Reset OTP verification
        $customer->save();

        return response()->json(['message' => 'Password reset successful'], 200);
    }
}
