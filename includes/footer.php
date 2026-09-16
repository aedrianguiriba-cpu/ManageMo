    <footer class="mt-3 py-2 text-center">
        <div class="container-fluid">
            <small>&copy; 2026 ManageMo &mdash; Pampanga State University Asset Management System. All rights reserved.</small>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.0/jquery.min.js"></script>
    <script src="<?php echo BASE_URL; ?>js/script.js"></script>
    <script>
    (function() {
        var start = Date.now();
        var minDisplayMs = 300; // avoid an unpleasant flash on very fast loads
        var maxWaitMs = 4000;   // safety net in case 'load' never fires cleanly

        function hideSkeleton() {
            var el = document.getElementById('skeletonLoader');
            if (!el) return;
            el.classList.add('sk-hidden');
            setTimeout(function() { el.remove(); }, 300);
        }
        function reveal() {
            var elapsed = Date.now() - start;
            setTimeout(hideSkeleton, Math.max(0, minDisplayMs - elapsed));
        }

        if (document.readyState === 'complete') {
            reveal();
        } else {
            window.addEventListener('load', reveal);
        }
        setTimeout(hideSkeleton, maxWaitMs);
    })();
    </script>
</body>
</html>
