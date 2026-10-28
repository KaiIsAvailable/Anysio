@props(['status'])

@php($status = strtolower(trim($status ?? 'Not recorded')))

<span class="px-2.5 py-0.5 rounded-full text-xs font-bold border uppercase
                                                @if($status === 'paid') bg-green-100 text-green-700 border-green-200
                                                @elseif($status === 'unpaid') bg-yellow-100 text-yellow-700 border-yellow-200
                                                @elseif($status === 'partial') bg-blue-100 text-blue-700 border-blue-200
                                                @elseif($status === 'overdue') bg-red-100 text-red-700 border-red-200
                                                @elseif($status === 'rejected') bg-red-100 text-red-700 border-red-200
                                                @elseif($status === 'void') bg-gray-100 text-gray-500 border-gray-200 line-through
                                                @else bg-slate-100 text-slate-800 border-slate-200
                                                @endif">
                                        {{ $status }}
                                    </span>
