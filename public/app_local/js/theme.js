/* ------------------------------------------------------------------------------
 *
 *  # Template configurator
 *
 *  Demo JS code for sliding panel with demo config
 *
 * ---------------------------------------------------------------------------- */


// Check localStorage on page load and set mathing theme/direction
// ------------------------------

(function () {
    (localStorage.getItem('l4-theme') == 'dark') && document.documentElement.setAttribute('data-color-theme', 'dark');
    localStorage.getItem('direction') == 'rtl' && document.getElementById("stylesheet").setAttribute('href', 'assets/css/rtl/all.min.css');
    localStorage.getItem('direction') == 'rtl' && document.documentElement.setAttribute('dir', 'rtl');
})();

var primaryTheme = 'light';

function setTheme(e) {
    var storageKey = 'l4-theme';
    var mode = e.checked ? "dark" : "light";
    document.documentElement.classList.add('no-transitions');
    if ((mode == primaryTheme)) {
        localStorage.removeItem(storageKey);
    }
    else {
        localStorage.setItem(storageKey, mode);
    }
    applyTheme(mode);
};

function applyTheme(mode) {
    if (mode == primaryTheme) {
        document.documentElement.removeAttribute('data-color-theme');
    } else {
        document.documentElement.setAttribute('data-color-theme', 'dark');
    }
}