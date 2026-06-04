<x-public-layout>
    <nav class="flex justify-between items-center py-4 px-12 bg-white sticky top-0 z-50 border-b border-gray-100 px-4 py-4">
        <a href="">
            <x-application-logo class="w-8 h-8 fill-current text-indigo-600" />
        </a>
        <div class="space-x-8 font-medium text-sm"> <a href="#features" class="text-gray-600 hover:text-indigo-600 transition">Features</a>
            <a href="#pricing" class="text-gray-600 hover:text-indigo-600 transition">Pricing</a>
            <a href="{{ route('login') }}" class="text-gray-900 hover:text-indigo-600 transition">Login</a>
        </div>
    </nav>

    <header class="py-24 px-12 bg-white max-w-7xl mx-auto flex flex-col md:flex-row items-center gap-12">
        <div class="md:w-1/2">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-50 text-indigo-600 text-sm font-semibold mb-6">
                <span class="w-2 h-2 rounded-full bg-indigo-600"></span> 
                Next-Gen Property Management
            </div>
            <h1 class="text-6xl font-extrabold text-gray-900 tracking-tight leading-tight">
                Empowering your <br><span class="text-indigo-600">Property Ambition</span>
            </h1>
            <p class="mt-6 text-xl text-gray-600 leading-relaxed">
                Anysio PMS provides the digital infrastructure to manage, optimize, and scale your real estate portfolio with precision.
            </p>
            <div class="mt-10 flex gap-4">
                <a href="{{ route('register') }}" class="bg-indigo-600 text-white px-8 py-4 rounded-xl font-bold hover:bg-indigo-700 transition shadow-lg shadow-indigo-200">Get Started</a>
                <a href="#features" class="px-8 py-4 rounded-xl font-semibold text-gray-700 hover:bg-gray-50 transition">View Demo</a>
            </div>
        </div>
    </header>

    <section id="features" class="py-24 bg-gray-50">
        <div class="max-w-7xl mx-auto px-12 text-center">
            <h2 class="text-4xl font-bold mb-4">Built for Evolution</h2>
            <p class="text-gray-600 mb-16 max-w-xl mx-auto">
                We are rapidly building the tools you need. Every month, Anysio becomes more powerful to help you manage your property empire.
            </p>
        </div>
    </section>

    <section id="pricing" class="py-24">
        <div class="max-w-7xl mx-auto px-12 text-center">
            <h2 class="text-4xl font-bold mb-4">Transparent Pricing</h2>
            <p class="text-gray-600 mb-16">Choose the plan that fits your current growth stage.</p>
            
            <div class="grid md:grid-cols-3 gap-8 max-w-5xl mx-auto">
                @foreach($packages as $package)
                    <div class="group border-2 border-gray-100 rounded-3xl p-10 hover:border-indigo-600 transition-all duration-300">
                        <h3 class="text-2xl font-bold">{{ $package->name }}</h3>
                        
                        {{-- 逻辑判断开始 --}}
                        @if($package->commission_rate > 0 && $package->price == 0)
                            {{-- 如果是佣金模式，显示比例 --}}
                            <p class="text-4xl font-bold mt-4">{{ $package->formatted_commission }}</p>
                        @else
                            {{-- 否则显示价格 --}}
                            <p class="text-4xl font-bold mt-4">{{ $package->formatted_price }}</p>
                        @endif
                        
                        <p class="text-sm text-gray-400 mb-6">{{ $package->price_mode }}</p>
                        {{-- 逻辑判断结束 --}}

                        <ul class="text-left space-y-3 mb-8 text-gray-600">
                            {{-- 基础额度 --}}
                            <li class="flex items-center gap-2">
                                <span class="text-indigo-500">✔</span> 
                                <span class="font-semibold">{{ $package->base_lease }}</span> Base Leases Included
                            </li>
                            
                            {{-- 扩容上限 --}}
                            <li class="flex items-center gap-2">
                                <span class="text-indigo-500">✔</span> 
                                Up to <span class="font-semibold">{{ $package->max_lease_limit }}</span> Total Leases
                            </li>

                            {{-- 超额单价 --}}
                            @if($package->max_lease_limit)
                                <li class="flex items-center gap-2 text-indigo-600">
                                    <span class="text-indigo-500">+</span> 
                                    {{ $package->formatted_extra_price }} per extra lease
                                </li>
                            @endif
                        </ul>

                        <a href="{{ route('register') }}?plan={{ $package->ref_code }}" 
                        class="block py-3 bg-gray-100 text-gray-900 rounded-lg transition-all duration-300 
                                group-hover:bg-indigo-600 group-hover:text-white">
                        Select {{ $package->name }}
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <footer class="py-12 px-12 bg-gray-50 text-gray-600 text-sm border-t border-gray-200">
        <div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-4 gap-8">
            <div>
                <h4 class="font-bold text-gray-900 mb-4">Anysio PMS</h4>
                <p>Empowering landlords and property managers with next-gen technology.</p>
            </div>
            <div>
                <h4 class="font-bold text-gray-900 mb-4">Company</h4>
                <ul class="space-y-2">
                    <li><a href="#">About Us</a></li>
                    <li><a href="#">Careers</a></li>
                    <li><a href="#">Status</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-bold text-gray-900 mb-4">Legal</h4>
                <ul class="space-y-2">
                    <li><a href="#">Privacy Policy</a></li>
                    <li><a href="#">Terms of Service</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-bold text-gray-900 mb-4">Registry</h4>
                <p>Anysio Technologies Sdn. Bhd.</p>
                <p>Reg No: [YOUR_SSM_NUMBER]</p>
            </div>
        </div>
        <div class="text-center mt-12 pt-8 border-t border-gray-200">
            &copy; {{ date('Y') }} Anysio PMS. All rights reserved.
        </div>
    </footer>
</x-public-layout>