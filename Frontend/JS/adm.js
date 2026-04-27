function abrirEditar(id, email) {
    document.getElementById('editId').value = id;
    document.getElementById('editEmail').value = email;
    document.getElementById('modalEditar').classList.add('ativo');
}

function abrirExcluir(id, email) {
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteEmail').innerText = email;
    document.getElementById('modalExcluir').classList.add('ativo');
}

function fecharModais() {
    document.querySelectorAll('.modal')
        .forEach(m => m.classList.remove('ativo'));
}