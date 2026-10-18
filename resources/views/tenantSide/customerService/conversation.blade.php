<x-customer-service.chat
    :ticket="$ticket"
    :current-role="$currentRole"
    :send-url="route($routePrefix.'.reply', $ticket)"
    :read-url="route($routePrefix.'.show', $ticket)"
    :mark-read-url="route($routePrefix.'.markRead', $ticket)"
    :can-send="$canSend"
    :can-attach="$canAttach"
    :context="\Illuminate\Support\Facades\Gate::allows('is-tenant') ? '' : $ticket->sourceLabel()"
    :messages="$messages"
    :embedded="true"
/>
