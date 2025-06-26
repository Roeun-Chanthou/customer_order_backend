<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Customer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Mail\SendCustomerOtp;
use Illuminate\Support\Facades\Mail;

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
            $customer = Customer::create([
                'email' => $request->email,
                'password' => bcrypt($request->password),
            ]);
        } else {
            if (!Hash::check($request->password, $customer->password)) {
                return response()->json(['message' => 'Invalid credentials'], 401);
            }
        }
        $otp = rand(100000, 999999);
        $customer->otp = $otp;
        $customer->otp_verified = false;
        $customer->save();

        // Send OTP via email
        Mail::to($customer->email)->send(new SendCustomerOtp($otp));

        return response()->json(['message' => 'OTP sent to email'], 200);
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
        if (!$customer || $customer->otp != $request->otp) {
            return response()->json(['message' => 'Invalid OTP'], 400);
        }

        $customer->otp_verified = true;
        $customer->save();

        return response()->json(['message' => 'OTP verified'], 200);
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
