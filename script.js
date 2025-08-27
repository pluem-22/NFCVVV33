// js/script.js

document.addEventListener('DOMContentLoaded', function() {
    // SweetAlert for Transaction Delete confirmation (used on transaction_history.php)
    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', function(event) {
            event.preventDefault(); // Prevent default link behavior
            const url = this.href;

            Swal.fire({
                title: 'คุณแน่ใจหรือไม่?',
                text: "คุณต้องการลบรายการนี้ใช่หรือไม่?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'ใช่, ลบเลย!',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = url; // Proceed with deletion
                }
            });
        });
    });

    // Handle Transaction Edit button click to populate modal (used on transaction_history.php)
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.dataset.id;
            const amount = this.dataset.amount;
            const customerName = this.dataset.customer_name;
            const type = this.dataset.type;

            document.getElementById('edit_id').value = id;
            document.getElementById('edit_amount').value = amount;
            document.getElementById('edit_customer_name').value = customerName;
            document.getElementById('edit_type').value = type;

            const editModal = new bootstrap.Modal(document.getElementById('editTransactionModal'));
            editModal.show();
        });
    });

    // SweetAlert for Product Delete confirmation (used on products.php)
    document.querySelectorAll('.delete-product-btn').forEach(button => {
        button.addEventListener('click', function(event) {
            event.preventDefault();
            const url = this.href;

            Swal.fire({
                title: 'คุณแน่ใจหรือไม่?',
                text: "คุณต้องการลบสินค้าชิ้นนี้ใช่หรือไม่?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'ใช่, ลบเลย!',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = url;
                }
            });
        });
    });

    // Handle Product Edit button click to populate modal (used on products.php)
    document.querySelectorAll('.edit-product-btn').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.dataset.id;
            const name = this.dataset.name;
            const price = this.dataset.price;
            const stock = this.dataset.stock;

            document.getElementById('edit_product_id').value = id;
            document.getElementById('edit_product_name').value = name;
            document.getElementById('edit_price').value = price;
            document.getElementById('edit_stock').value = stock;

            const editProductModal = new bootstrap.Modal(document.getElementById('editProductModal'));
            editProductModal.show();
        });
    });

    // Sidebar Toggle (Universal for all pages)
    document.getElementById('sidebarToggle').addEventListener('click', function() {
        document.getElementById('wrapper').classList.toggle('toggled');
    });

    // Close sidebar if a menu item is clicked on small screens (Universal)
    document.querySelectorAll('.custom-list-item').forEach(item => {
        item.addEventListener('click', function() {
            if (window.innerWidth < 768) {
                document.getElementById('wrapper').classList.remove('toggled');
            }
        });
    });
});