<?php
/**
 * Indra Hotel - Shared Admin Footer
 */
?>
        </main>
        
        <!-- Admin Footer -->
        <footer class="bg-white border-t border-stone-200 px-6 py-4 text-xs text-stone-500 flex flex-col sm:flex-row items-center justify-between gap-2">
            <div>
                <strong>SoftBook</strong> v<?= APP_VERSION ?> • <?= e(hotel_name()) ?>
            </div>
            <div class="flex items-center gap-4">
                <span>Server Time: <?= date('Y-m-d H:i:s') ?></span>
            </div>
        </footer>

    </div>

</body>
</html>
