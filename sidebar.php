<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$role = $_SESSION['role'] ?? '';
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <i class="fas fa-building"></i>
        </div>
        <div class="brand">
            <h3>PPMP System</h3>
            <p>Municipality of Balilihan</p>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <ul>
            <?php if ($role === 'Admin'): ?>
                <!-- Admin Menu -->
                <li class="<?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
                    <a href="<?php echo APP_URL; ?>/pages/admin/dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                
                <li class="menu-header">Management</li>
                
                <li class="<?php echo $currentPage === 'users' ? 'active' : ''; ?>">
                    <a href="<?php echo APP_URL; ?>/pages/admin/users.php">
                        <i class="fas fa-users"></i>
                        <span>Manage Users</span>
                    </a>
                </li>
                
                <li class="<?php echo $currentPage === 'departments' ? 'active' : ''; ?>">
                    <a href="<?php echo APP_URL; ?>/pages/admin/departments.php">
                        <i class="fas fa-building"></i>
                        <span>Departments</span>
                    </a>
                </li>
                
                <li class="<?php echo $currentPage === 'fiscal_year' ? 'active' : ''; ?>">
                    <a href="<?php echo APP_URL; ?>/pages/admin/fiscal_year.php">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Fiscal Years</span>
                    </a>
                </li>
                
                <li class="<?php echo $currentPage === 'procurement_modes' ? 'active' : ''; ?>">
                    <a href="<?php echo APP_URL; ?>/pages/admin/procurement_modes.php">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Procurement Modes</span>
                    </a>
                </li>
                
                <li class="menu-header">Monitoring</li>
                
                <li class="<?php echo $currentPage === 'all_ppmps' ? 'active' : ''; ?>">
                    <a href="<?php echo APP_URL; ?>/pages/admin/all_ppmps.php">
                        <i class="fas fa-file-alt"></i>
                        <span>All PPMPS</span>
                    </a>
                </li>
                
                <li class="<?php echo $currentPage === 'audit_trail' ? 'active' : ''; ?>">
                    <a href="<?php echo APP_URL; ?>/pages/admin/audit_trail.php">
                        <i class="fas fa-history"></i>
                        <span>Audit Trail</span>
                    </a>
                </li>
                
                <li class="menu-header">System</li>
                
                <li class="<?php echo $currentPage === 'settings' ? 'active' : ''; ?>">
                    <a href="<?php echo APP_URL; ?>/pages/admin/settings.php">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                </li>
                
            <?php else: ?>
                <!-- User Menu (Staff, Dept Head, etc.) -->
                <li class="<?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
                    <a href="<?php echo APP_URL; ?>/pages/user/dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                
                <li class="<?php echo $currentPage === 'ppmp_planning' ? 'active' : ''; ?>">
                    <a href="#">
                        <i class="fas fa-clipboard-list"></i>
                        <span>PPMP Planning</span>
                    </a>
                </li>
                
                <li class="<?php echo $currentPage === 'my_ppmps' ? 'active' : ''; ?>">
                    <a href="#">
                        <i class="fas fa-file-alt"></i>
                        <span>My PPMPS</span>
                    </a>
                </li>
                
                <li class="<?php echo in_array($role, ['Department Head', 'Budget Officer', 'BAC', 'Mayor']) ? '' : 'hidden'; ?> <?php echo $currentPage === 'approvals' ? 'active' : ''; ?>">
                    <a href="#">
                        <i class="fas fa-check-circle"></i>
                        <span>Approvals</span>
                        <?php if (isset($pendingCount) && $pendingCount > 0): ?>
                            <span class="badge"><?php echo $pendingCount; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                
                <li class="<?php echo $currentPage === 'fund_certification' ? 'active' : ''; ?>">
                    <a href="#">
                        <i class="fas fa-certificate"></i>
                        <span>Fund Certification</span>
                    </a>
                </li>
                
                <li class="<?php echo $currentPage === 'payment_monitoring' ? 'active' : ''; ?>">
                    <a href="#">
                        <i class="fas fa-money-bill-wave"></i>
                        <span>Payment Monitoring</span>
                    </a>
                </li>
                
                <li class="<?php echo $currentPage === 'reports' ? 'active' : ''; ?>">
                    <a href="#">
                        <i class="fas fa-chart-bar"></i>
                        <span>Reports</span>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
    
    <div class="sidebar-footer">
        <a href="<?php echo APP_URL; ?>/pages/admin/logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>
