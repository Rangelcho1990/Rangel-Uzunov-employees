const toggle = document.getElementById('toggle-upload');
const panel = document.getElementById('upload-panel');
toggle.addEventListener('click', () => {
    panel.hidden = !panel.hidden;
    toggle.setAttribute('aria-expanded', String(!panel.hidden));
    if (!panel.hidden) panel.querySelector('input[type="file"]').focus();
});
