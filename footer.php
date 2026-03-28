    </div><!-- End app-container -->
    
    <script src="<?php echo APP_URL; ?>/assets/js/main.js"></script>
    <?php if (isset($extraJS)): ?>
        <?php foreach ($extraJS as $js): ?>
            <script src="<?php echo APP_URL . $js; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
