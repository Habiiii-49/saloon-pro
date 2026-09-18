<?php
/**
 * Admin Panel - Footer
 * Closes layout containers and loads scripts.
 */
?>
        </main><!-- /.admin-content -->
    </div><!-- /.admin-main -->
</div><!-- /.admin-layout -->

<!-- Bootstrap 5.3 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Admin JS -->
<script src="<?php echo SITE_URL; ?>/admin/assets/js/admin.js"></script>
<?php if (!empty($extraJs)): ?>
<script src="<?php echo SITE_URL; ?>/<?php echo sanitize($extraJs); ?>"></script>
<?php endif; ?>
</body>
</html>