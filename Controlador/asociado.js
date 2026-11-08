const formulario = document.getElementById("forRegistro");

formulario.addEventListener("submit", async function(event) {
    // Evitar recargar la pagina al enviar el formulario 
    event.preventDefault();

    const botonguardar = formulario.querySelector('button[type="submit"]');

    //lee los campos del formulario y los guarda en un objeto
    const asociado = Object.fromEntries(new FormData(formulario).entries());

    botonguardar.disabled = true; //Desabilitar el boton mienstras se procesa la solicitud
    
    try{
      const respuesta = await fetcf(formulario.action, {
        method : 'POST', 
        headers:{'Content-Type': 'application/json'}, 
        body: JSON.stringify(asociado)
    });
    
      // pasar las respuestas Json a un objeto de JavaScript
      const resultado = await respuesta.json();
      document.getElementById("mensajeRegistro").textContent = "Guardando...";

      document.getElementById("mensajeRegistro").dataset.tipo="";

      document .getElementById("mensajeRegistro").textContent = resultado.mensaje;
    
      if(respuesta.ok && resultado.success){
        formulario.reset(); //Limpiar el formulario si la respuesta es exitosa
      }
    } catch (error) {
        console.error("Error al enviar el formulario:", error);
        document.getElementById("mensajeRegistro").textContent =
         "Ocurrio un error al enviar el formulario. Por favor, intente nuevamente.";         
    } finally {
        botonguardar.disabled = false; //Habilitar el boton despues de procesar la solicitud
    }
});
