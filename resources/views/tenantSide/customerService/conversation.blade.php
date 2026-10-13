<x-customer-service.chat
    :ticket="$ticket"
    :current-role="$currentRole"
    :send-url="route('tenants.customerService.reply', $ticket)"
    :read-url="route('tenants.customerService.show', $ticket)"
    :can-send="$canSend"
    :messages="$messages"
    :embedded="true"
/>
