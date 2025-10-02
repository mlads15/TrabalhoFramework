function carregarBanco() {
    const servidor = document.querySelector('input[name="servidor"]').value;
    const usuario = document.querySelector('input[name="usuario"]').value;
    const senha = document.querySelector('input[name="senha"]').value;
    const select = document.querySelector('select[name="banco"]');
    const carregando = document.getElementById('carregando');

    // Verifica se os campos obrigatórios estão preenchidos
    if (!servidor || !usuario) {
        return;
    }

    // Mostra mensagem de carregando
    carregando.innerHTML = 'Carregando bancos de dados...';

    // Limpa o select
    select.innerHTML = '<option value="">Selecione um banco</option>';

    // Faz a requisição para buscar os bancos
    fetch(`creator.php?id=1`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Erro na requisição');
            }
            return response.text();
        })
        .then(data => {
            select.innerHTML = '<option value="">Selecione um banco</option>' + data;
            carregando.innerHTML = '';
        })
        .catch(error => {
            console.error('Erro:', error);
            carregando.innerHTML = 'Erro ao carregar bancos';
            select.innerHTML = '<option value="">Erro ao carregar</option>';
        });
}
