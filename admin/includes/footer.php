<?php if (!defined('ADMIN_PAGE')) die('Direct access not permitted'); ?>
            </div> <!-- Close container -->
        </main> <!-- Close admin-content -->
    </div> <!-- Close admin-layout -->
    
    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>
    
    <script>
        // Toast notification function
        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            
            const icon = type === 'success' ? '✅' : '⚠️';
            
            toast.innerHTML = `
                <span class="toast-icon">${icon}</span>
                <span class="toast-message">${message}</span>
            `;
            
            container.appendChild(toast);
            
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(100px)';
                setTimeout(() => toast.remove(), 300);
            }, 4000);
        }
        
        // Show flash messages from PHP
        <?php if (isset($_SESSION['admin_success'])): ?>
            showToast('<?php echo addslashes($_SESSION['admin_success']); ?>', 'success');
            <?php unset($_SESSION['admin_success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['admin_error'])): ?>
            showToast('<?php echo addslashes($_SESSION['admin_error']); ?>', 'error');
            <?php unset($_SESSION['admin_error']); ?>
        <?php endif; ?>
    </script>
</body>
</html>