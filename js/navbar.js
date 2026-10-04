document.addEventListener("DOMContentLoaded", function () {

    const navToggle = document.getElementById("navToggle");
    const navMenu = document.getElementById("navMenu");

    if (!navToggle || !navMenu) {
        return;
    }

    navToggle.addEventListener("click", function () {

        navMenu.classList.toggle("show");
        navToggle.classList.toggle("active");

        const isOpen = navMenu.classList.contains("show");

        navToggle.setAttribute("aria-expanded", isOpen);
    });


    // Close menu when a link is clicked
    const navLinks = navMenu.querySelectorAll("a");

    navLinks.forEach(function (link) {

        link.addEventListener("click", function () {

            navMenu.classList.remove("show");
            navToggle.classList.remove("active");

            navToggle.setAttribute("aria-expanded", "false");
        });

    });

});