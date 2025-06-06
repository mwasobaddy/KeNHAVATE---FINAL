@component('mail::message')
# New Account {{ ucfirst($appealType) }} Appeal Submitted

A user has submitted an appeal for their {{ $appealType === 'ban' ? 'banned' : 'suspended' }} account.

## User Details
- **Name:** {{ $user->name }}
- **Email:** {{ $user->email }}
- **Account Status:** {{ ucfirst($user->account_status) }}
- **Appeal Type:** {{ ucfirst($appealType) }}

## Appeal Message
{{ $appeal->message }}

## Appeal Details
- **Submitted At:** {{ $appeal->created_at->format('M d, Y \a\t g:i A T') }}
- **IP Address:** {{ $appeal->ip_address }}
- **Status:** {{ ucfirst($appeal->status) }}

@component('mail::button', ['url' => config('app.url') . '/dashboard/admin'])
Review in Admin Dashboard
@endcomponent

Please review this appeal and take appropriate action. You can respond to the user directly through the admin dashboard or contact them via their registered email address.

## Next Steps
1. Log into the KeNHAVATE Innovation Portal
2. Navigate to the Admin Dashboard
3. Review the appeal details and user's account history
4. Make a decision to approve, reject, or request more information
5. Respond to the user with your decision and reasoning

Thanks,<br>
{{ config('app.name') }} System
@endcomponent
