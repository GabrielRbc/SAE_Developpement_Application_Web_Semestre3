console.log("Script importExport.js chargé !");
document.addEventListener("DOMContentLoaded", () => {
    const btnExport = document.getElementById("btn-export");
    const btnImport = document.getElementById("btn-import");
    const sectionExport = document.getElementById("section-export");
    const sectionImport = document.getElementById("section-import");

    const hiddenTypeExport = document.getElementById("hiddenTypeExport");
    const hiddenFileType = document.getElementById("hiddenFileType");
    const hiddenChamps = document.getElementById("hiddenChamps");
    const hiddenTypeImport = document.getElementById("hiddenTypeImport");

    // Bascule import/export
    btnExport.addEventListener("click", () => {
        btnExport.classList.add("active");
        btnImport.classList.remove("active");
        sectionExport.classList.remove("d-none");
        sectionImport.classList.add("d-none");
    });
    btnImport.addEventListener("click", () => {
        btnImport.classList.add("active");
        btnExport.classList.remove("active");
        sectionImport.classList.remove("d-none");
        sectionExport.classList.add("d-none");
    });

    // Gestion cartes options
    document.querySelectorAll(".export-options").forEach(group => {
        group.querySelectorAll(".option-card").forEach(card => {
            card.addEventListener("click", () => {
                group.querySelectorAll(".option-card").forEach(c => c.classList.remove("active"));
                card.classList.add("active");

                if(card.dataset.exportType) {
                    hiddenTypeExport.value = card.dataset.exportType;
                    if(card.dataset.exportType === "personnalise") {
                        document.getElementById("export-personnalise").classList.remove("d-none");
                    } else {
                        document.getElementById("export-personnalise").classList.add("d-none");
                        hiddenChamps.value = "";
                    }
                }
                if(card.dataset.fileType) hiddenFileType.value = card.dataset.fileType;
                if(card.dataset.importType) hiddenTypeImport.value = card.dataset.importType;
            });
        });
    });

    // Champs personnalisés – correction du nextSibling
    document.querySelectorAll("#export-personnalise input[type=checkbox]").forEach(chk => {
        chk.addEventListener("change", () => {
            const selected = Array.from(document.querySelectorAll("#export-personnalise input[type=checkbox]:checked"))
                .map(c => c.closest('label').textContent.trim());
            hiddenChamps.value = selected.join(",");
        });
    });
});