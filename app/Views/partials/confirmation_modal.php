<dialog id="confirmation-modal" class="modal" aria-labelledby="confirmation-title">
    <div class="modal-box">
        <h2 id="confirmation-title" class="text-xl font-semibold">Confirmar acción</h2>
        <p id="confirmation-message" class="py-4 text-base-content/70">Confirma que deseas continuar.</p>
        <div class="modal-action">
            <form method="dialog"><button class="btn">Cancelar</button></form>
            <button id="confirmation-accept" type="button" class="btn btn-error">Confirmar</button>
        </div>
    </div>
    <form method="dialog" class="modal-backdrop"><button aria-label="Cerrar confirmación">Cerrar</button></form>
</dialog>
