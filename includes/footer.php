<?php require_once __DIR__ . '/config.php'; ?>
                </div>
            </div>
        </div>

        <!-- FIXED FOOTER -->
        <footer class="sticky-footer bg-white fixed-footer">
            <div class="container my-auto">
                <div class="copyright text-center my-auto">
                    <span>Copyright &copy; <?= date('Y') ?> Flour Mill Management System</span>
                </div>
            </div>
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
        // ===== SIDEBAR TOGGLE =====
        function isMobile() { return $(window).width() < 768; }

        // Desktop sidebar toggle (button at bottom of sidebar)
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

        // Topbar hamburger toggle (visible on all screens)
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
        // Close sidebar on overlay click
        $('#sidebarOverlay').on('click', function() {
            $('.sidebar').removeClass('mobile-open');
            $('#sidebarOverlay').removeClass('active');
        });

        // Neutralize any SB Admin 2 auto-toggle on resize (uses sidebar-toggled)
        $('body').removeClass('sidebar-toggled');
        $('.sidebar').removeClass('toggled');
        // Also clean them up whenever resize might have added them
        $(window).on('resize', function() {
            $('body').removeClass('sidebar-toggled');
            $('.sidebar').removeClass('toggled');
        });

        // Initialize DataTables on all tables with class 'datatable'
        $('.datatable').each(function() {
            if (!$.fn.dataTable.isDataTable(this)) {
                $(this).DataTable({
                    pageLength: 25,
                    order: [[0, 'desc']]
                });
            }
        });

        // Auto-hide alerts after 5 seconds
        $('.alert-auto').delay(5000).fadeOut('slow');

        // On mobile: close sidebar when a page link is clicked inside it
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
