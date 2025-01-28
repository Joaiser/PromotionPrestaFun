document.addEventListener("DOMContentLoaded", function () {
    const modal = document.getElementById("promotionModal");
    const modalClose = document.getElementById("promotionModalClose");

    // Mostrar el modal si no se ha visto antes
    if (!getCookie("promotionModalSeen")) {
        modal.classList.add("visible");
    }

    // Cerrar el modal al hacer clic en el botón de cierre
    modalClose.onclick = function () {
        modal.classList.remove("visible");
        setCookie("promotionModalSeen", "true", 365); // Establecer cookie por 1 año
        document.cookie = "promotion_modal=;path=/;expires=Thu, 01 Jan 1970 00:00:00 UTC;";
    };
    

    // Cerrar el modal al hacer clic fuera de él
    window.onclick = function (event) {
        if (event.target === modal) {
            modal.classList.remove("visible");
            setCookie("promotionModalSeen", "true", 365); // Establecer cookie por 1 año
        }
    };

    // Función para obtener una cookie
    function getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(";").shift();
    }

    // Función para establecer una cookie
    function setCookie(name, value, days) {
        const date = new Date();
        date.setTime(date.getTime() + days * 24 * 60 * 60 * 1000);
        document.cookie = `${name}=${value};path=/;expires=${date.toUTCString()}`;
    }
<<<<<<< HEAD
});
=======
});
>>>>>>> dea31c5 (Modulo hecho con bd y acabado)
