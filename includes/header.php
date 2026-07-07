<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . $base_url . "auth/login.php");
    exit;
}

$bookings_active     = in_array($active_page ?? '', ['booking_add','booking_list']);
$suppliers_active    = in_array($active_page ?? '', ['supplier_add','supplier_list','supplier_ledger','supplier_payment']);
$products_active     = in_array($active_page ?? '', ['product_add','product_list','product_stock']);
$production_active   = in_array($active_page ?? '', ['production_add','production_list','production_report','warehouse_stock']);
$customers_active    = in_array($active_page ?? '', ['customer_add','customer_list','customer_ledger','customer_receipt']);
$sales_active        = in_array($active_page ?? '', ['sale_add','sale_list','sale_return','sale_return_list']);
$expenses_active     = in_array($active_page ?? '', ['expense_category','expense_add','expense_list']);
$accounts_active     = in_array($active_page ?? '', ['cash_book','bank_book','general_ledger']);
$masters_active      = in_array($active_page ?? '', ['vehicles','drivers','bags','brokers','warehouses']);
$arrivals_active     = in_array($active_page ?? '', ['arrival_add','arrival_list','arrival_stock']);
$farmers_active      = in_array($active_page ?? '', ['farmer_list','farmer_ledger','farmer_payment']);
$stock_active        = in_array($active_page ?? '', ['stock_adjustment','stock_ledger','stock_report']);
$reports_active      = in_array($active_page ?? '', ['daily_summary']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Flour Mill - <?= $page_title ?? 'Dashboard' ?></title>

    <link href="<?= $asset_path ?>vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    <link href="<?= $asset_path ?>css/sb-admin-2.min.css" rel="stylesheet">
    <link href="<?= $asset_path ?>vendor/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">
    <script src="<?= $asset_path ?>vendor/jquery/jquery.min.js"></script>

    <style>
        :root {
            --navy: #1B2A4A;
            --navy-dark: #0F1A30;
            --gold: #D4A017;
            --gold-dark: #B8860B;
            --gold-light: #FDF3D0;
        }

        /* ===== FIXED HEADER ===== */
        .fixed-topbar {
            position: fixed;
            top: 0;
            right: 0;
            left: 225px;
            z-index: 1030;
            transition: left 0.2s ease;
        }
        body.sidebar-collapsed .fixed-topbar {
            left: 0;
        }

        /* ===== FIXED FOOTER ===== */
        .fixed-footer {
            position: fixed;
            bottom: 0;
            right: 0;
            left: 225px;
            z-index: 1030;
            transition: left 0.2s ease;
        }
        body.sidebar-collapsed .fixed-footer {
            left: 0;
        }

        /* ===== MAIN CONTENT ===== */
        #content-wrapper {
            padding-top: 80px;
            padding-bottom: 60px;
            margin-left: 225px;
            min-height: 100vh;
            transition: margin-left 0.2s ease;
        }
        body.sidebar-collapsed #content-wrapper {
            margin-left: 0;
        }

        /* ===== SIDEBAR OVERRIDES ===== */
        .sidebar {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            bottom: 0 !important;
            z-index: 1040 !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
            transition: left 0.2s ease !important;
        }
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }
        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.2);
            border-radius: 3px;
        }
        .sidebar::-webkit-scrollbar-track {
            background: transparent;
        }
        /* Desktop collapsed: slide sidebar left off-screen */
        body.sidebar-collapsed .sidebar {
            left: -225px !important;
        }
        /* Neutralize SB Admin 2's .toggled width override (it sets width:0) */
        .sidebar.toggled {
            width: 14rem !important;
        }

        /* ===== OVERLAY on mobile when sidebar open ===== */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1035;
        }
        .sidebar-overlay.active {
            display: block;
        }

        /* Mobile: sidebar becomes overlay */
        @media (max-width: 768px) {
            .fixed-topbar {
                left: 0;
            }
            .fixed-footer {
                left: 0;
            }
            #content-wrapper {
                margin-left: 0;
            }
            .sidebar {
                width: 260px !important;
                left: -260px !important;
                transition: left 0.3s ease !important;
            }
            .sidebar.mobile-open {
                left: 0 !important;
            }
            /* Override SB Admin 2 mobile styles for our full-width sidebar */
            .sidebar .nav-item .nav-link {
                text-align: left !important;
                padding: 0.75rem 1rem !important;
                width: 260px !important;
            }
            .sidebar .nav-item .nav-link span {
                display: inline !important;
                font-size: 0.85rem !important;
            }
            .sidebar .nav-item .nav-link i {
                margin-right: 0.25rem !important;
            }
            .sidebar .nav-item .collapse,
            .sidebar .nav-item .collapsing {
                position: relative !important;
                left: 0 !important;
                top: 0 !important;
                background: rgba(0,0,0,0.15) !important;
                border-radius: 0.35rem !important;
                margin: 0 0.5rem 0.5rem !important;
                box-shadow: none !important;
            }
            .sidebar .nav-item .collapse .collapse-inner {
                background: transparent !important;
                border-radius: 0 !important;
                box-shadow: none !important;
                padding: 0.25rem 0 !important;
            }
        }

        /* ===== COLOR THEME ===== */
        .bg-gradient-primary {
            background: linear-gradient(180deg, var(--navy) 10%, var(--navy-dark) 100%) !important;
        }
        .sidebar-brand {
            background-color: var(--navy-dark) !important;
        }
        .sidebar-dark .nav-item .nav-link:focus,
        .sidebar-dark .nav-item .nav-link:hover,
        .sidebar-dark .nav-item.active > .nav-link {
            color: var(--gold) !important;
        }
        .sidebar-dark .nav-item.active > .nav-link::after {
            background-color: var(--gold) !important;
        }
        .sidebar-dark #sidebarToggle:hover {
            background-color: rgba(212, 160, 23, 0.2) !important;
        }
        .btn-primary {
            background-color: var(--gold) !important;
            border-color: var(--gold) !important;
            color: #fff !important;
        }
        .btn-primary:hover, .btn-primary:focus {
            background-color: var(--gold-dark) !important;
            border-color: var(--gold-dark) !important;
        }
        .btn-outline-primary {
            color: var(--gold) !important;
            border-color: var(--gold) !important;
        }
        .btn-outline-primary:hover {
            background-color: var(--gold) !important;
            color: #fff !important;
        }
        .text-primary {
            color: var(--gold) !important;
        }
        .border-left-primary {
            border-left-color: var(--gold) !important;
        }
        .border-bottom-primary {
            border-bottom-color: var(--gold) !important;
        }
        a {
            color: var(--gold-dark);
        }
        a:hover {
            color: #9A7200;
        }
        .card-header {
            background-color: var(--gold-light) !important;
            border-bottom: 1px solid #F5E6B8 !important;
        }
        .card-header .font-weight-bold {
            color: var(--navy) !important;
        }
        .table .thead-dark th {
            background-color: var(--navy) !important;
            border-color: var(--navy-dark) !important;
        }
        .page-item.active .page-link {
            background-color: var(--navy) !important;
            border-color: var(--navy) !important;
        }
        .page-link {
            color: var(--navy) !important;
        }
        .bg-gradient-primary .sidebar-brand-text,
        .bg-gradient-primary .sidebar-brand-icon i {
            color: var(--gold) !important;
        }
        .sidebar .sidebar-heading {
            color: rgba(212, 160, 23, 0.7) !important;
        }
        .sidebar-dark .nav-item .nav-link[data-toggle="collapse"]::after {
            color: rgba(255,255,255,0.35) !important;
        }
        .sidebar-dark .nav-item.active .nav-link[data-toggle="collapse"]::after {
            color: var(--gold) !important;
        }
        .collapse-inner {
            background: transparent !important;
            margin: 0 6px !important;
        }
        .collapse-inner .collapse-header {
            color: rgba(212, 160, 23, 0.6) !important;
            font-size: 0.65rem !important;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 0.3rem 0.5rem !important;
        }
        .collapse-inner .collapse-item {
            color: rgba(255,255,255,0.75) !important;
            padding: 0.35rem 0.5rem !important;
            border-radius: 4px !important;
            font-size: 0.85rem !important;
            transition: all 0.15s ease;
        }
        .collapse-inner .collapse-item:hover,
        .collapse-inner .collapse-item:focus {
            background: rgba(212, 160, 23, 0.15) !important;
            color: #fff !important;
        }
        .collapse-inner .collapse-item.active {
            background: rgba(212, 160, 23, 0.12) !important;
            color: var(--gold) !important;
            font-weight: 600;
        }
        .collapse-inner .collapse-item i {
            width: 16px;
            text-align: center;
        }

        /* ===== FORM STYLES ===== */
        .form-control {
            width: 100% !important;
            height: 42px !important;
            font-size: 14px !important;
            padding: 8px 14px !important;
            border-radius: 5px !important;
            border: 1px solid #d1d3e2 !important;
            box-sizing: border-box !important;
        }
        .form-control:focus {
            border-color: var(--gold) !important;
            box-shadow: 0 0 0 0.2rem rgba(212, 160, 23, 0.25) !important;
        }
        .form-control-sm {
            height: 32px !important;
            font-size: 13px !important;
            padding: 4px 10px !important;
            width: auto !important;
        }
        select.form-control {
            height: 42px !important;
        }
        textarea.form-control {
            min-height: 80px !important;
            resize: vertical !important;
        }
        label {
            font-size: 14px !important;
            font-weight: 600 !important;
            margin-bottom: 5px !important;
            color: #333 !important;
        }
        .form-group {
            margin-bottom: 16px !important;
        }
        .btn {
            padding: 8px 20px !important;
            font-size: 14px !important;
            border-radius: 5px !important;
            font-weight: 500 !important;
        }
        .btn-sm {
            padding: 6px 14px !important;
            font-size: 13px !important;
        }

        /* ===== DATA VALIDATION ===== */
        input.error, select.error, textarea.error {
            border-color: #e74a3b !important;
            box-shadow: 0 0 0 0.2rem rgba(231, 74, 59, 0.25) !important;
        }
        .error-text {
            color: #e74a3b;
            font-size: 12px;
            margin-top: 4px;
        }

        /* ===== CARD DASHBOARD ===== */
        .card-dashboard .card-body { padding: 1.25rem; }
        .card-dashboard .h5 { font-size: 1.5rem; }

        @media print {
            body * { visibility: hidden; }
            .container-fluid, .container-fluid * { visibility: visible; }
            .container-fluid { position: absolute; left: 0; top: 0; width: 100%; }
            .sidebar, .fixed-topbar, .fixed-footer, .btn, .no-print,
            .dataTables_filter, .dataTables_length, .dataTables_info, .dataTables_paginate { display: none !important; }
            #content-wrapper { margin: 0 !important; padding: 0 !important; }
            .card { border: none !important; box-shadow: none !important; }
            .card-header { background: #f8f9fc !important; border: 1px solid #ddd !important; }
            .table { border-collapse: collapse !important; }
            .table th, .table td { border: 1px solid #000 !important; }
            @page { margin: 0.5in; }
        }
    </style>
</head>
<body id="page-top">
    <div id="wrapper">
        <!-- SIDEBAR -->
        <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="<?= $base_url ?>dashboard.php">
                <div class="sidebar-brand-icon">
                    <i class="fas fa-wheat-alt"></i>
                </div>
                <div class="sidebar-brand-text mx-3">Flour Mill</div>
            </a>

            <hr class="sidebar-divider my-0">

            <li class="nav-item <?= ($active_page ?? '') === 'dashboard' ? 'active' : '' ?>">
                <a class="nav-link" href="<?= $base_url ?>dashboard.php">
                    <i class="fas fa-fw fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <hr class="sidebar-divider">
            <div class="sidebar-heading">Masters</div>
            <li class="nav-item <?= $masters_active ? 'active' : '' ?>">
                <a class="nav-link <?= $masters_active ? '' : 'collapsed' ?>" href="#" data-toggle="collapse" data-target="#collapseMasters" aria-expanded="<?= $masters_active ? 'true' : 'false' ?>">
                    <i class="fas fa-fw fa-database"></i>
                    <span>Masters</span>
                </a>
                <div id="collapseMasters" class="collapse <?= $masters_active ? 'show' : '' ?>" data-parent="#accordionSidebar">
                    <div class="py-2 collapse-inner rounded">
                        <a class="collapse-item <?= navActive('vehicles', $active_page ?? '') ?>" href="<?= $base_url ?>modules/masters/vehicles.php"><i class="fas fa-truck fa-sm mr-1"></i> Vehicles</a>
                        <a class="collapse-item <?= navActive('drivers', $active_page ?? '') ?>" href="<?= $base_url ?>modules/masters/drivers.php"><i class="fas fa-id-card fa-sm mr-1"></i> Drivers</a>
                        <a class="collapse-item <?= navActive('bags', $active_page ?? '') ?>" href="<?= $base_url ?>modules/masters/bags.php"><i class="fas fa-shopping-bag fa-sm mr-1"></i> Bag Types</a>
                        <a class="collapse-item <?= navActive('brokers', $active_page ?? '') ?>" href="<?= $base_url ?>modules/masters/brokers.php"><i class="fas fa-handshake fa-sm mr-1"></i> Brokers</a>
                        <a class="collapse-item <?= navActive('warehouses', $active_page ?? '') ?>" href="<?= $base_url ?>modules/masters/warehouses.php"><i class="fas fa-warehouse fa-sm mr-1"></i> Warehouses</a>
                    </div>
                </div>
            </li>

            <hr class="sidebar-divider">
            <div class="sidebar-heading">Booking</div>
            <li class="nav-item <?= $bookings_active ? 'active' : '' ?>">
                <a class="nav-link <?= $bookings_active ? '' : 'collapsed' ?>" href="#" data-toggle="collapse" data-target="#collapseBookings" aria-expanded="<?= $bookings_active ? 'true' : 'false' ?>">
                    <i class="fas fa-fw fa-file-signature"></i>
                    <span>Booking</span>
                </a>
                <div id="collapseBookings" class="collapse <?= $bookings_active ? 'show' : '' ?>" data-parent="#accordionSidebar">
                    <div class="py-2 collapse-inner rounded">
                        <a class="collapse-item <?= navActive('booking_add', $active_page ?? '') ?>" href="<?= $base_url ?>modules/bookings/add.php"><i class="fas fa-plus-circle fa-sm mr-1"></i> New Booking</a>
                        <a class="collapse-item <?= navActive('booking_list', $active_page ?? '') ?>" href="<?= $base_url ?>modules/bookings/list.php"><i class="fas fa-list fa-sm mr-1"></i> Booking List</a>
                    </div>
                </div>
            </li>

            <hr class="sidebar-divider">
            <div class="sidebar-heading">Wheat Arrival</div>
            <li class="nav-item <?= $arrivals_active ? 'active' : '' ?>">
                <a class="nav-link <?= $arrivals_active ? '' : 'collapsed' ?>" href="#" data-toggle="collapse" data-target="#collapseArrivals" aria-expanded="<?= $arrivals_active ? 'true' : 'false' ?>">
                    <i class="fas fa-fw fa-truck-loading"></i>
                    <span>Arrivals</span>
                </a>
                <div id="collapseArrivals" class="collapse <?= $arrivals_active ? 'show' : '' ?>" data-parent="#accordionSidebar">
                    <div class="py-2 collapse-inner rounded">
                        <a class="collapse-item <?= navActive('arrival_add', $active_page ?? '') ?>" href="<?= $base_url ?>modules/arrivals/add.php"><i class="fas fa-plus-circle fa-sm mr-1"></i> New Arrival</a>
                        <a class="collapse-item <?= navActive('arrival_list', $active_page ?? '') ?>" href="<?= $base_url ?>modules/arrivals/list.php"><i class="fas fa-list fa-sm mr-1"></i> Arrival Register</a>
                        <a class="collapse-item <?= navActive('arrival_stock', $active_page ?? '') ?>" href="<?= $base_url ?>modules/arrivals/stock.php"><i class="fas fa-warehouse fa-sm mr-1"></i> Raw Material Stock</a>
                    </div>
                </div>
            </li>

            <hr class="sidebar-divider">
            <div class="sidebar-heading">Farmers</div>
            <li class="nav-item <?= $farmers_active ? 'active' : '' ?>">
                <a class="nav-link <?= $farmers_active ? '' : 'collapsed' ?>" href="#" data-toggle="collapse" data-target="#collapseFarmers" aria-expanded="<?= $farmers_active ? 'true' : 'false' ?>">
                    <i class="fas fa-fw fa-tractor"></i>
                    <span>Farmers</span>
                </a>
                <div id="collapseFarmers" class="collapse <?= $farmers_active ? 'show' : '' ?>" data-parent="#accordionSidebar">
                    <div class="py-2 collapse-inner rounded">
                        <a class="collapse-item <?= navActive('farmer_list', $active_page ?? '') ?>" href="<?= $base_url ?>modules/farmers/list.php"><i class="fas fa-list fa-sm mr-1"></i> Farmer List</a>
                        <a class="collapse-item <?= navActive('farmer_ledger', $active_page ?? '') ?>" href="<?= $base_url ?>modules/farmers/ledger.php"><i class="fas fa-book fa-sm mr-1"></i> Ledger</a>
                        <a class="collapse-item <?= navActive('farmer_payment', $active_page ?? '') ?>" href="<?= $base_url ?>modules/farmers/payment.php"><i class="fas fa-money-bill-wave fa-sm mr-1"></i> Payment</a>
                    </div>
                </div>
            </li>

            <hr class="sidebar-divider">
            <div class="sidebar-heading">Suppliers</div>
            <li class="nav-item <?= $suppliers_active ? 'active' : '' ?>">
                <a class="nav-link <?= $suppliers_active ? '' : 'collapsed' ?>" href="#" data-toggle="collapse" data-target="#collapseSuppliers" aria-expanded="<?= $suppliers_active ? 'true' : 'false' ?>">
                    <i class="fas fa-fw fa-truck"></i>
                    <span>Suppliers</span>
                </a>
                <div id="collapseSuppliers" class="collapse <?= $suppliers_active ? 'show' : '' ?>" data-parent="#accordionSidebar">
                    <div class="py-2 collapse-inner rounded">
                        <a class="collapse-item <?= navActive('supplier_add', $active_page ?? '') ?>" href="<?= $base_url ?>modules/suppliers/add.php"><i class="fas fa-plus-circle fa-sm mr-1"></i> Add Supplier</a>
                        <a class="collapse-item <?= navActive('supplier_list', $active_page ?? '') ?>" href="<?= $base_url ?>modules/suppliers/list.php"><i class="fas fa-list fa-sm mr-1"></i> Supplier List</a>
                        <a class="collapse-item <?= navActive('supplier_ledger', $active_page ?? '') ?>" href="<?= $base_url ?>modules/suppliers/ledger.php"><i class="fas fa-book fa-sm mr-1"></i> Ledger</a>
                        <a class="collapse-item <?= navActive('supplier_payment', $active_page ?? '') ?>" href="<?= $base_url ?>modules/suppliers/payment.php"><i class="fas fa-money-bill-wave fa-sm mr-1"></i> Payment</a>
                    </div>
                </div>
            </li>

            <hr class="sidebar-divider">
            <div class="sidebar-heading">Production</div>
            <li class="nav-item <?= $production_active ? 'active' : '' ?>">
                <a class="nav-link <?= $production_active ? '' : 'collapsed' ?>" href="#" data-toggle="collapse" data-target="#collapseProduction" aria-expanded="<?= $production_active ? 'true' : 'false' ?>">
                    <i class="fas fa-fw fa-industry"></i>
                    <span>Production</span>
                </a>
                <div id="collapseProduction" class="collapse <?= $production_active ? 'show' : '' ?>" data-parent="#accordionSidebar">
                    <div class="py-2 collapse-inner rounded">
                        <a class="collapse-item <?= navActive('production_add', $active_page ?? '') ?>" href="<?= $base_url ?>modules/production/add.php"><i class="fas fa-plus-circle fa-sm mr-1"></i> New Crush</a>
                        <a class="collapse-item <?= navActive('production_list', $active_page ?? '') ?>" href="<?= $base_url ?>modules/production/list.php"><i class="fas fa-list fa-sm mr-1"></i> Production List</a>
                        <a class="collapse-item <?= navActive('production_report', $active_page ?? '') ?>" href="<?= $base_url ?>modules/production/report.php"><i class="fas fa-chart-bar fa-sm mr-1"></i> Extraction Report</a>
                        <a class="collapse-item <?= navActive('warehouse_stock', $active_page ?? '') ?>" href="<?= $base_url ?>modules/stock/warehouse_stock.php"><i class="fas fa-warehouse fa-sm mr-1"></i> Warehouse Stock</a>
                    </div>
                </div>
            </li>

            <hr class="sidebar-divider">
            <div class="sidebar-heading">Products & Stock</div>
            <li class="nav-item <?= $products_active || $stock_active ? 'active' : '' ?>">
                <a class="nav-link <?= $products_active || $stock_active ? '' : 'collapsed' ?>" href="#" data-toggle="collapse" data-target="#collapseProducts" aria-expanded="<?= $products_active || $stock_active ? 'true' : 'false' ?>">
                    <i class="fas fa-fw fa-boxes"></i>
                    <span>Stock</span>
                </a>
                <div id="collapseProducts" class="collapse <?= $products_active || $stock_active ? 'show' : '' ?>" data-parent="#accordionSidebar">
                    <div class="py-2 collapse-inner rounded">
                        <a class="collapse-item <?= navActive('product_list', $active_page ?? '') ?>" href="<?= $base_url ?>modules/products/list.php"><i class="fas fa-list fa-sm mr-1"></i> Products</a>
                        <a class="collapse-item <?= navActive('product_stock', $active_page ?? '') ?>" href="<?= $base_url ?>modules/products/stock.php"><i class="fas fa-warehouse fa-sm mr-1"></i> Current Stock</a>
                        <a class="collapse-item <?= navActive('stock_ledger', $active_page ?? '') ?>" href="<?= $base_url ?>modules/stock/ledger.php"><i class="fas fa-book fa-sm mr-1"></i> Stock Ledger</a>
                        <a class="collapse-item <?= navActive('stock_adjustment', $active_page ?? '') ?>" href="<?= $base_url ?>modules/stock/adjustment.php"><i class="fas fa-sliders-h fa-sm mr-1"></i> Adjustment</a>
                    </div>
                </div>
            </li>

            <hr class="sidebar-divider">
            <div class="sidebar-heading">Sales</div>
            <li class="nav-item <?= $sales_active ? 'active' : '' ?>">
                <a class="nav-link <?= $sales_active ? '' : 'collapsed' ?>" href="#" data-toggle="collapse" data-target="#collapseSales" aria-expanded="<?= $sales_active ? 'true' : 'false' ?>">
                    <i class="fas fa-fw fa-cash-register"></i>
                    <span>Sales</span>
                </a>
                <div id="collapseSales" class="collapse <?= $sales_active ? 'show' : '' ?>" data-parent="#accordionSidebar">
                    <div class="py-2 collapse-inner rounded">
                        <a class="collapse-item <?= navActive('sale_add', $active_page ?? '') ?>" href="<?= $base_url ?>modules/sales/add.php"><i class="fas fa-plus-circle fa-sm mr-1"></i> New Sale</a>
                        <a class="collapse-item <?= navActive('sale_list', $active_page ?? '') ?>" href="<?= $base_url ?>modules/sales/list.php"><i class="fas fa-list fa-sm mr-1"></i> Sales List</a>
                        <a class="collapse-item <?= navActive('sale_return', $active_page ?? '') ?>" href="<?= $base_url ?>modules/sales/return_add.php"><i class="fas fa-undo-alt fa-sm mr-1"></i> New Return</a>
                    </div>
                </div>
            </li>

            <hr class="sidebar-divider">
            <div class="sidebar-heading">Customers</div>
            <li class="nav-item <?= $customers_active ? 'active' : '' ?>">
                <a class="nav-link <?= $customers_active ? '' : 'collapsed' ?>" href="#" data-toggle="collapse" data-target="#collapseCustomers" aria-expanded="<?= $customers_active ? 'true' : 'false' ?>">
                    <i class="fas fa-fw fa-users"></i>
                    <span>Customers</span>
                </a>
                <div id="collapseCustomers" class="collapse <?= $customers_active ? 'show' : '' ?>" data-parent="#accordionSidebar">
                    <div class="py-2 collapse-inner rounded">
                        <a class="collapse-item <?= navActive('customer_add', $active_page ?? '') ?>" href="<?= $base_url ?>modules/customers/add.php"><i class="fas fa-plus-circle fa-sm mr-1"></i> Add Customer</a>
                        <a class="collapse-item <?= navActive('customer_list', $active_page ?? '') ?>" href="<?= $base_url ?>modules/customers/list.php"><i class="fas fa-list fa-sm mr-1"></i> Customer List</a>
                        <a class="collapse-item <?= navActive('customer_ledger', $active_page ?? '') ?>" href="<?= $base_url ?>modules/customers/ledger.php"><i class="fas fa-book fa-sm mr-1"></i> Ledger</a>
                        <a class="collapse-item <?= navActive('customer_receipt', $active_page ?? '') ?>" href="<?= $base_url ?>modules/customers/receipt.php"><i class="fas fa-money-bill-wave fa-sm mr-1"></i> Payments</a>
                    </div>
                </div>
            </li>

            <hr class="sidebar-divider">
            <div class="sidebar-heading">Expenses</div>
            <li class="nav-item <?= $expenses_active ? 'active' : '' ?>">
                <a class="nav-link <?= $expenses_active ? '' : 'collapsed' ?>" href="#" data-toggle="collapse" data-target="#collapseExpenses" aria-expanded="<?= $expenses_active ? 'true' : 'false' ?>">
                    <i class="fas fa-fw fa-coins"></i>
                    <span>Expenses</span>
                </a>
                <div id="collapseExpenses" class="collapse <?= $expenses_active ? 'show' : '' ?>" data-parent="#accordionSidebar">
                    <div class="py-2 collapse-inner rounded">
                        <a class="collapse-item <?= navActive('expense_add', $active_page ?? '') ?>" href="<?= $base_url ?>modules/expenses/add.php"><i class="fas fa-plus-circle fa-sm mr-1"></i> Add Expense</a>
                        <a class="collapse-item <?= navActive('expense_list', $active_page ?? '') ?>" href="<?= $base_url ?>modules/expenses/list.php"><i class="fas fa-list fa-sm mr-1"></i> Expense List</a>
                    </div>
                </div>
            </li>

            <hr class="sidebar-divider">
            <div class="sidebar-heading">Accounts</div>
            <li class="nav-item <?= $accounts_active ? 'active' : '' ?>">
                <a class="nav-link <?= $accounts_active ? '' : 'collapsed' ?>" href="#" data-toggle="collapse" data-target="#collapseAccounts" aria-expanded="<?= $accounts_active ? 'true' : 'false' ?>">
                    <i class="fas fa-fw fa-wallet"></i>
                    <span>Accounts</span>
                </a>
                <div id="collapseAccounts" class="collapse <?= $accounts_active ? 'show' : '' ?>" data-parent="#accordionSidebar">
                    <div class="py-2 collapse-inner rounded">
                        <a class="collapse-item <?= navActive('cash_book', $active_page ?? '') ?>" href="<?= $base_url ?>modules/accounts/cash_book.php"><i class="fas fa-money-bill-wave fa-sm mr-1"></i> Cash Book</a>
                        <a class="collapse-item <?= navActive('bank_book', $active_page ?? '') ?>" href="<?= $base_url ?>modules/accounts/bank_book.php"><i class="fas fa-university fa-sm mr-1"></i> Bank Book</a>
                        <a class="collapse-item <?= navActive('general_ledger', $active_page ?? '') ?>" href="<?= $base_url ?>modules/accounts/general_ledger.php"><i class="fas fa-book fa-sm mr-1"></i> General Ledger</a>
                    </div>
                </div>
            </li>

            <hr class="sidebar-divider">
            <div class="sidebar-heading">Reports</div>
            <li class="nav-item <?= $reports_active ? 'active' : '' ?>">
                <a class="nav-link <?= $reports_active ? '' : 'collapsed' ?>" href="#" data-toggle="collapse" data-target="#collapseReports" aria-expanded="<?= $reports_active ? 'true' : 'false' ?>">
                    <i class="fas fa-fw fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
                <div id="collapseReports" class="collapse <?= $reports_active ? 'show' : '' ?>" data-parent="#accordionSidebar">
                    <div class="py-2 collapse-inner rounded">
                        <a class="collapse-item <?= navActive('daily_summary', $active_page ?? '') ?>" href="<?= $base_url ?>modules/reports/daily_summary.php"><i class="fas fa-calendar-day fa-sm mr-1"></i> Daily Summary</a>

                    </div>
                </div>
            </li>

            <hr class="sidebar-divider d-none d-md-block">

            <div class="text-center d-none d-md-inline">
                <button class="rounded-circle border-0" id="sidebarToggle"></button>
            </div>
        </ul>
        <!-- END SIDEBAR -->

        <!-- Sidebar overlay for mobile -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <!-- FIXED HEADER -->
        <nav class="navbar navbar-expand navbar-light bg-white topbar fixed-topbar mb-0 static-top shadow">
            <button id="sidebarToggleTop" class="btn btn-link rounded-circle mr-3">
                <i class="fa fa-bars"></i>
            </button>
            <ul class="navbar-nav ml-auto">
                <li class="nav-item dropdown no-arrow">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown">
                        <span class="mr-2 d-none d-lg-inline text-gray-600 small"><?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['email'] ?? 'User') ?></span>
                        <i class="fas fa-user-circle fa-2x text-gray-400"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in" aria-labelledby="userDropdown">
                        <a class="dropdown-item" href="<?= $base_url ?>auth/logout.php">
                            <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>
                            Logout
                        </a>
                    </div>
                </li>
            </ul>
        </nav>
        <!-- END FIXED HEADER -->

        <!-- CONTENT WRAPPER -->
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <div class="container-fluid">
