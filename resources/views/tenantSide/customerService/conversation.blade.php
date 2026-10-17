<x-customer-service.chat
    :ticket="$ticket"
    :current-role="$currentRole"
    :send-url="route($routePrefix.'.reply', $ticket)"
    :read-url="route($routePrefix.'.show', $ticket)"
    :can-send="$canSend"
    :can-attach="$canAttach"
    :context="auth()->user()->isTenant() ? '' : $ticket->sourceLabel()"
    :messages="$messages"
    :embedded="true"
/>
