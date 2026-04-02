module.exports = function() {

    var btn     = document.getElementById("mobile-menu-btn");
    var menu    = document.getElementById('_menu');

    btn.addEventListener('click', function() {
        menu.classList.toggle("visible");
    });

};