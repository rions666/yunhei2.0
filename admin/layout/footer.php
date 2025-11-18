    </main>

    <script>

    function confirmDelete(message = '确定要删除这条记录吗？') {
        return confirm(message);
    }

    function showLoading(element) {
        const originalText = element.innerHTML;
        element.innerHTML = '<i class="loading"></i> 处理中...';
        element.disabled = true;
        return originalText;
    }

    function hideLoading(element, originalText) {
        element.innerHTML = originalText;
        element.disabled = false;
    }

    function showAlert(message, type = 'info') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type}`;
        alertDiv.innerHTML = message;
        
        const main = document.querySelector('.admin-main');
        main.insertBefore(alertDiv, main.firstChild);

        setTimeout(() => {
            alertDiv.remove();
        }, 3000);
    }

    function toggleAllCheckboxes(masterCheckbox) {
        const checkboxes = document.querySelectorAll('input[name="selected[]"]');
        checkboxes.forEach(checkbox => {
            checkbox.checked = masterCheckbox.checked;
        });
    }

    function batchAction(action) {
        const selected = document.querySelectorAll('input[name="selected[]"]:checked');
        if (selected.length === 0) {
            alert('请先选择要操作的项目');
            return false;
        }
        
        const ids = Array.from(selected).map(checkbox => checkbox.value);
        
        if (action === 'delete') {
            if (!confirm(`确定要删除选中的 ${selected.length} 条记录吗？`)) {
                return false;
            }
        }
        
        return true;
    }

    function scrollToTop() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        
        const successAlert = document.getElementById('successAlert');
        if (successAlert) {
            setTimeout(function() {
                successAlert.style.animation = 'fadeOut 0.3s ease-out';
                setTimeout(function() {
                    successAlert.remove();
                }, 300);
            }, 3000);
        }
    });
    </script>
</body>
</html>