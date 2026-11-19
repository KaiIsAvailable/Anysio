@props(['maintenance', 'staffOptions' => [], 'staffCreateData' => null])
@if(in_array($maintenance->status, ['pending','maintaining'])) @can('maintenance.assign')
<x-maintenance.assign-modal :maintenance="$maintenance" :staff-options="$staffOptions" :staff-create-data="$staffCreateData" />
@endcan @endif
@if($maintenance->status === 'maintaining') @can('maintenance.complete')
<x-maintenance.complete-modal :maintenance="$maintenance" />
@endcan @endif
