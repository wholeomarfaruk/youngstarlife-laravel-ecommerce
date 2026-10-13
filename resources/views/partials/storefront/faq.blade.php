{{-- Settings → Website texts → FAQ (shared by home, shop, category and product pages). $page: home | shop | category | product --}}
@if (site("content.faq_on.$page", true) && ($faq = array_filter(site('content.faq', []))))
    <section id="faq" class=" mb-3">
        <div class="container">
            <h1 class="fs-5 fw-bold bg-primary-color text-center py-3 px-3 text-white">{{ site()->text('content.faq_title') }}</h1>
            <ul class="list-inline fs-6 fw-medium">
                @foreach ($faq as $line)
                    <li><i class="fa-solid fa-angles-right text-primary-color"></i> {{ strtr($line, ['{store}' => site()->storeName(), '{whatsapp}' => site()->whatsapp()]) }}</li>
                @endforeach
            </ul>
        </div>
    </section>
@endif
