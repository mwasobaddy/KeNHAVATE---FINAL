<?php

namespace App\Services;

use App\Models\OTP;
use App\Models\User;
use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * KeNHAVATE Innovation Portal - OTP Authentication Service
 * Handles OTP generation, validation, and email delivery
 * Features: 15-minute validity, single-use, 60-second resend cooldown
 */
class OTPService
{
    protected AuditService $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Generate or reuse existing OTP for email verification
     */
    public function generateOTP(string $email, string $purpose = 'login'): array
    {
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email address provided');
        }

        // Check for existing unexpired OTP
        $existingOTP = OTP::where('email', $email)
            ->where('purpose', $purpose)
            ->where('expires_at', '>', now())
            ->where('used_at', null)
            ->first();

        if ($existingOTP) {
            // Check if we can resend (60-second cooldown)
            $canResend = $existingOTP->created_at->addSeconds(60) <= now();
            
            if (!$canResend) {
                $waitTime = 60 - now()->diffInSeconds($existingOTP->created_at);
                throw new \Exception("Please wait {$waitTime} seconds before requesting a new OTP");
            }

            // Resend existing OTP
            $this->sendOTPEmail($email, $existingOTP->otp_code, $purpose);
            
            // Log resend action
            $this->auditService->log('otp_resent', 'otp', $existingOTP->id);

            return [
                'otp' => $existingOTP->otp_code,
                'expires_at' => $existingOTP->expires_at,
                'action' => 'resent',
                'expires_in_minutes' => now()->diffInMinutes($existingOTP->expires_at)
            ];
        }

        // Generate new 6-digit OTP
        $otpCode = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = now()->addMinutes(15);

        // Create OTP record
        $otp = OTP::create([
            'email' => $email,
            'otp_code' => $otpCode,
            'purpose' => $purpose,
            'expires_at' => $expiresAt,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        // Send OTP via email
        $this->sendOTPEmail($email, $otpCode, $purpose);

        // Log OTP generation
        $this->auditService->log('otp_generated', 'otp', $otp->id);

        return [
            'otp' => $otpCode,
            'expires_at' => $expiresAt,
            'action' => 'generated',
            'expires_in_minutes' => 15
        ];
    }

    /**
     * Validate OTP and mark as used
     */
    public function validateOTP(string $email, string $otpCode, string $purpose = 'login'): bool
    {
        $otp = OTP::where('email', $email)
            ->where('otp_code', $otpCode)
            ->where('purpose', $purpose)
            ->where('expires_at', '>', now())
            ->where('used_at', null)
            ->first();

        if (!$otp) {
            // Log failed validation attempt
            $this->auditService->log('otp_validation_failed', 'otp', null, null, [
                'email' => $email,
                'attempted_code' => $otpCode,
                'purpose' => $purpose
            ]);
            
            return false;
        }

        // Mark OTP as used
        $otp->update([
            'used_at' => now(),
            'validated_ip' => request()->ip(),
            'validated_user_agent' => request()->userAgent(),
        ]);

        // Log successful validation
        $this->auditService->log('otp_validated', 'otp', $otp->id);

        return true;
    }

    /**
     * Verify OTP and return the associated user for authentication
     */
    public function verifyOTP(string $email, string $otpCode, string $purpose = 'login'): User
    {
        // First validate the OTP
        if (!$this->validateOTP($email, $otpCode, $purpose)) {
            throw new \Exception('Invalid or expired verification code. Please try again.');
        }

        // Find and return the user
        $user = User::where('email', $email)->first();
        
        if (!$user) {
            throw new \Exception('User account not found.');
        }

        return $user;
    }

    /**
     * Clean up expired OTPs (for scheduled cleanup)
     */
    public function cleanupExpiredOTPs(): int
    {
        $deletedCount = OTP::where('expires_at', '<', now()->subHours(24))->delete();
        
        if ($deletedCount > 0) {
            $this->auditService->log('otp_cleanup', 'otp', null, null, [
                'deleted_count' => $deletedCount
            ]);
        }

        return $deletedCount;
    }

    /**
     * Check if user has exceeded OTP request limits
     */
    public function checkRateLimit(string $email): bool
    {
        $recentAttempts = OTP::where('email', $email)
            ->where('created_at', '>', now()->subHour())
            ->count();

        return $recentAttempts < 5; // Max 5 OTP requests per hour
    }

    /**
     * Send OTP via email
     */
    protected function sendOTPEmail(string $email, string $otpCode, string $purpose): void
    {
        $data = [
            'otp_code' => $otpCode,
            'purpose' => $purpose,
            'expires_in' => 15,
            'email' => $email
        ];

        try {
            // Send OTP email
            Mail::to($email)->send(new \App\Mail\OTPMail($data));
            
            // Log successful email send
            \Log::info("OTP Email Sent Successfully", [
                'to' => $email,
                'purpose' => $purpose,
                'expires_at' => now()->addMinutes(15)
            ]);
        } catch (\Exception $e) {
            // Log email failure but don't throw exception to avoid blocking OTP generation
            \Log::error("Failed to send OTP email", [
                'to' => $email,
                'error' => $e->getMessage(),
                'otp' => $otpCode // Keep for debugging, remove in production
            ]);
            
            // In development, you might want to throw the exception
            // throw new \Exception("Failed to send OTP email: " . $e->getMessage());
        }
    }

    /**
     * Get OTP statistics for admin dashboard
     */
    public function getOTPStats(): array
    {
        return [
            'total_generated_today' => OTP::whereDate('created_at', today())->count(),
            'total_validated_today' => OTP::whereDate('created_at', today())
                ->whereNotNull('used_at')->count(),
            'expired_today' => OTP::whereDate('created_at', today())
                ->where('expires_at', '<', now())
                ->whereNull('used_at')->count(),
            'success_rate' => $this->calculateSuccessRate(),
        ];
    }

    /**
     * Calculate OTP validation success rate
     */
    protected function calculateSuccessRate(): float
    {
        $totalGenerated = OTP::whereDate('created_at', '>=', now()->subDays(7))->count();
        $totalValidated = OTP::whereDate('created_at', '>=', now()->subDays(7))
            ->whereNotNull('used_at')->count();

        return $totalGenerated > 0 ? round(($totalValidated / $totalGenerated) * 100, 2) : 0;
    }
}
