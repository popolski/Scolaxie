    </main>
</div>
<script>
document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') return;
    var details = document.querySelector('details[open]');
    if (!details) return;
    details.open = false;
    var summary = details.querySelector('summary');
    if (summary) summary.focus();
});
</script>
</body>
</html>
