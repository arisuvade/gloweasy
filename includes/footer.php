</main>
</div>

<footer class="py-4">
    <div class="container text-center">
        <p class="mb-1">Experience the healing touch of...</p>
        <p class="mb-0">© <?= date('Y') ?> Bali Ayurveda Spa - All Rights Reserved</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.logout-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                Swal.fire({
                    title: 'Ready to Leave?',
                    text: 'Are you sure you want to logout?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: 'var(--secondary-green)',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, Logout',
                    cancelButtonText: 'Cancel',
                    backdrop: 'rgba(26, 95, 62, 0.2)'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = link.getAttribute('href');
                    }
                });
            });
        });
    });
</script>
</body>
</html>