<template>
    <footer class="text-center text-gray-500 pt-8 pb-4 text-xs print:text-[9px]">
        <!-- Información Bancaria -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-px text-xs text-white text-center font-semibold rounded-lg overflow-hidden max-w-4xl mx-auto mb-6 print:grid-cols-2">
            <div class="bg-sky-700 p-2 break-words">
                <p><b>BANORTE M.N.</b> | {{ quote.is_spanish_template ? 'CUENTA' : 'ACCOUNT' }}: 1180403344 | CLABE: 072 320 011804033446</p>
            </div>
            <div class="bg-red-700 p-2 break-words">
                <p><b>BANORTE USD</b> | {{ quote.is_spanish_template ? 'CUENTA' : 'ACCOUNT' }}: 1181103856 | CLABE: 072 320 011811038560</p>
            </div>
        </div>

        <!-- Contacto -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-gray-600 mb-4">
            <div class="font-bold text-sm">
                 www.emblemas<b class="text-sky-600">3</b><b class="text-red-600">d</b>.com
            </div>
            <div>
                <i class="fas fa-mobile-alt"></i> 333 46 46 485 <br>
                <i class="fas fa-phone-alt"></i> (33) 38 33 82 09
            </div>
             <div>
                <i class="fas fa-envelope"></i> j.sherman@emblemas<b class="text-sky-600">3</b><b class="text-red-600">d</b>.com
            </div>
        </div>
        
         <!-- Descripción de la empresa -->
        <p class="leading-tight max-w-4xl mx-auto mt-4 print:text-gray-500" style="font-size: 10px;">
            {{ quote.is_spanish_template
                ? 'Emblemas de alta calidad, somos los mejores fabricantes. Ramo automotriz, electrodomésticos, electrónica, textil, calzado, muebles y juguetes. En división electrónica, somos desarrolladores de tecnología. Conoce nuestras nuevas memorias USB personalizadas desde el molde, son exclusivos. En división automotriz somos fabricantes especialistas en emblemas cromados, porta placas, llaveros, porta documentos, placas de estireno. Lo nuevo, LLAVERO USB, diseño original y personalizado, todo con molde único para tu empresa (personalización total y exclusiva).'
                : 'High-quality emblems, we are the best manufacturers. Automotive, home appliances, electronics, textile, footwear, furniture, and toys sectors. In the electronics division, we are technology developers. Discover our new custom USB drives from the mold, they are exclusive. In the automotive division, we are specialist manufacturers of chrome emblems, license plate holders, keychains, document holders, styrene plates. What\'s new, USB KEYCHAIN, original and personalized design, all with a unique mold for your company (total and exclusive customization).'
            }}
        </p>
    </footer>
</template>

<script>
export default {
    name: 'QuoteFooter',
    props: {
        quote: { type: Object, required: true }
    }
}
</script>