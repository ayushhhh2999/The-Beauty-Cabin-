</main>
<footer class="bc-footer mt-5 py-4">
    <div class="container d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
        <div>&copy; <?= date('Y') ?> The Beauty Cabin. All rights reserved.</div>
        <div>
            <a href="<?= e(url('services.php')) ?>">Services</a> &middot;
            <a href="<?= e(url('contact.php')) ?>">Contact</a> &middot;
            <a href="<?= e(url('login.php')) ?>">Staff sign in</a>
        </div>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(url('assets/js/script.js')) ?>"></script>
</body>
</html>
