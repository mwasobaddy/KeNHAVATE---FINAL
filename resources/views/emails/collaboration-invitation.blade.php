@php
    $acceptUrl = url("/collaborations/accept/{$collaboration->id}");
    $declineUrl = url("/collaborations/decline/{$collaboration->id}");
@endphp

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Collaboration Invitation</title>
</head>
<body style="font-family: Arial, sans-serif; background: #F8EBD5; color: #231F20;">
    <h2 style="color: #231F20;">You have been invited to collaborate on an idea!</h2>
    <p>Hello {{ $collaboration->collaborator->first_name }},</p>
    <p><strong>{{ $inviter->name }}</strong> has invited you to collaborate on the idea: <strong>{{ $idea->title }}</strong>.</p>
    @if($collaboration->invitation_message)
        <blockquote style="border-left: 4px solid #FFF200; margin: 1em 0; padding: 0.5em 1em; background: #fffbe6;">{{ $collaboration->invitation_message }}</blockquote>
    @endif
    <p>
        <a href="{{ $acceptUrl }}" style="background: #FFF200; color: #231F20; padding: 10px 20px; text-decoration: none; border-radius: 4px; margin-right: 10px;">Accept Invitation</a>
        <a href="{{ $declineUrl }}" style="background: #9B9EA4; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 4px;">Decline</a>
    </p>
    <p>If you have questions, please contact {{ $inviter->email }}.</p>
    <hr>
    <small>KeNHAVATE Innovation Portal</small>
</body>
</html>
