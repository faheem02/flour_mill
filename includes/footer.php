<?php require_once __DIR__ . '/config.php'; ?>
                </div>
            </div>

        <footer style="background:#fff; border-top:1px solid #e3e6f0; padding:12px 0; text-align:center;">
            <span class="text-muted small">Copyright &copy; <?= date('Y') ?> Flour Mill Management System</span>
        </footer>
        </div>

    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <script src="<?= $asset_path ?>vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="<?= $asset_path ?>vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="<?= $asset_path ?>vendor/datatables/jquery.dataTables.min.js"></script>
    <script src="<?= $asset_path ?>vendor/datatables/dataTables.bootstrap4.min.js"></script>
    <script src="<?= $asset_path ?>js/sb-admin-2.min.js"></script>

    <script>
    $(document).ready(function() {
        function isMobile() { return $(window).width() < 768; }

        $('#sidebarToggle').off('click');
        $('#sidebarToggle').on('click', function(e) {
            e.preventDefault();
            if (isMobile()) {
                $('.sidebar').toggleClass('mobile-open');
                $('#sidebarOverlay').toggleClass('active');
            } else {
                $('body').toggleClass('sidebar-collapsed');
            }
        });

        $('#sidebarToggleTop').off('click');
        $('#sidebarToggleTop').on('click', function(e) {
            e.preventDefault();
            if (isMobile()) {
                $('.sidebar').toggleClass('mobile-open');
                $('#sidebarOverlay').toggleClass('active');
            } else {
                $('body').toggleClass('sidebar-collapsed');
            }
        });
        $('#sidebarOverlay').on('click', function() {
            $('.sidebar').removeClass('mobile-open');
            $('#sidebarOverlay').removeClass('active');
        });

        $('body').removeClass('sidebar-toggled');
        $('.sidebar').removeClass('toggled');
        $(window).on('resize', function() {
            $('body').removeClass('sidebar-toggled');
            $('.sidebar').removeClass('toggled');
        });

        $('.datatable').each(function() {
            if (!$.fn.dataTable.isDataTable(this)) {
                $(this).DataTable({ pageLength: 25, order: [[0, 'desc']] });
            }
        });

        $('.alert-auto').delay(5000).fadeOut('slow');

        $('.sidebar .collapse-item').on('click', function() {
            if (isMobile()) {
                $('.sidebar').removeClass('mobile-open');
                $('#sidebarOverlay').removeClass('active');
            }
        });
    });
    </script>
</body>
</html>
