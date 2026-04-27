
if (typeof modalEditarLoaded === 'undefined') {
    
    // ========== VARIÁVEIS GLOBAIS ==========
    let modalEditar = null;
    let turmaIdAtual = null;
    
    // Mapeamento de dias da semana para bitmask
    const diasMap = {
        'seg': 1,   // Segunda (1 << 0)
        'ter': 2,   // Terça (1 << 1)
        'qua': 4,   // Quarta (1 << 2)
        'qui': 8,   // Quinta (1 << 3)
        'sex': 16   // Sexta (1 << 4)
    };

    /// ========== FUNÇÕES AUXILIARES ==========

/**
 * Converte bitmask (int) para array de dias: ['seg','ter',...]
 * Ex: 5 (00101) => ['seg','qua']
 */
function bitmaskParaDias(mask) {
  const dias = [];
  if (mask & 1) dias.push('seg');   // 1
  if (mask & 2) dias.push('ter');   // 2
  if (mask & 4) dias.push('qua');   // 4
  if (mask & 8) dias.push('qui');   // 8
  if (mask & 16) dias.push('sex');  // 16
  return dias;
}

/**
 * Formata o nome do dia pra exibição
 */
function formatarNomeDia(dia) {
  const nomes = {
    seg: 'Segunda-feira',
    ter: 'Terça-feira',
    qua: 'Quarta-feira',
    qui: 'Quinta-feira',
    sex: 'Sexta-feira'
  };
  return nomes[dia] || dia;
}

    

    /**
     * Carrega os dias atuais da turma via AJAX
     * @param {number} idTurma - ID da turma
     */
    async function carregarDiasTurma(idTurma) {
        try {
            console.log("📡 Carregando dias da turma ID:", idTurma);
            
            const response = await fetch(`../PHP/carregar_dias_turma.php?id_turma=${idTurma}`);
            if (!response.ok) {
                throw new Error(`Erro HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            console.log("📥 Resposta da API:", data);
            
            if (data.success && data.dias_semana !== undefined) {
                // Converte a máscara de bits para dias
                const diasSelecionados = bitmaskParaDias(data.dias_semana);
                
                console.log("✅ Dias convertidos:", diasSelecionados);
                
                // Limpa todos os checkboxes primeiro
                const checkboxes = document.querySelectorAll('#modalEditar input[name="dias_semana[]"]');
                checkboxes.forEach(cb => {
                    cb.checked = false;
                    cb.parentElement.style.opacity = "0.6";
                });
                
                // Marca os checkboxes correspondentes
                diasSelecionados.forEach(dia => {
                    const checkbox = document.querySelector(`#modalEditar input[name="dias_semana[]"][value="${dia}"]`);
                    if (checkbox) {
                        checkbox.checked = true;
                        checkbox.parentElement.style.opacity = "1";
                        console.log("✓ Marcado checkbox:", dia);
                    }
                });
                
                // Se não houver dias selecionados, alerta
                if (diasSelecionados.length === 0) {
                    console.warn("⚠️ Nenhum dia selecionado para esta turma");
                }
                
            } else {
                console.error("❌ Erro na resposta:", data.error || "Resposta inválida");
                alert("Erro ao carregar dias da turma: " + (data.error || "Resposta inválida"));
            }
            
        } catch (error) {
            console.error("❌ Erro ao carregar dias da turma:", error);
            // Não mostra alerta para não interromper o fluxo
        }
    }

    /**
     * Fecha o modal de edição
     */
    function fecharModalEditar() {
        if (modalEditar) {
            modalEditar.classList.remove("is-open");
            modalEditar.setAttribute("aria-hidden", "true");
        }
        turmaIdAtual = null;
        const previewDiv = document.getElementById('previewEditar');
        if (previewDiv) {
            previewDiv.innerHTML = '';
            previewDiv.style.display = 'none';
        }
    }

    // ========== EVENT LISTENERS - CONFIGURAÇÃO ==========

    /**
     * Configura todos os event listeners
     */
    function configurarEventListeners() {
  // Evento para abrir modal ao clicar no botão de editar
  document.addEventListener('click', function(e) {
    const btnEdit = e.target.closest('.btn-edit');
    if (btnEdit) {
      e.preventDefault();
      e.stopPropagation();
      abrirModalEditar(btnEdit);
    }
  });

  // Fechar modal editar com o botão X
  const closeBtnEditar = document.querySelector('[data-close-editar]');
  if (closeBtnEditar) closeBtnEditar.addEventListener('click', fecharModalEditar);

  // Botão cancelar
  const btnCancelarEditar = document.getElementById('btnCancelarEditar');
  if (btnCancelarEditar) btnCancelarEditar.addEventListener('click', fecharModalEditar);

  // Fechar modal editar ao clicar fora
  document.addEventListener('click', function(e) {
    if (modalEditar && e.target === modalEditar) fecharModalEditar();
  });

  // Fechar modal editar com ESC
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && modalEditar && modalEditar.classList.contains('is-open')) {
      fecharModalEditar();
    }
  });
} // ✅ ESSA CHAVE ESTAVA FALTANDO

    // ========== PRÉ-VISUALIZAÇÃO ==========

    /**
     * Função principal de pré-visualização
     */
    async function atualizarPreviewEditar() {
        const previewDiv = document.getElementById('previewEditar');
        if (!previewDiv) {
            console.error("❌ Elemento previewEditar não encontrado!");
            return;
        }
        
        // Mostrar loading
        previewDiv.innerHTML = '<div style="padding:20px;text-align:center;color:#666;background:#f8f9fa;border-radius:8px;">' +
                               '<div class="spinner" style="display:inline-block;width:20px;height:20px;border:3px solid #ddd;border-top-color:#2b7;border-radius:50%;animation:spin 1s linear infinite;margin-right:10px;"></div>' +
                               'Gerando pré-visualização...</div>';
        previewDiv.style.display = 'block';
        
        // Adicionar animação CSS
        const style = document.createElement('style');
        style.textContent = '@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }';
        document.head.appendChild(style);
        
        try {
            // Coletar dados do formulário
            const dados = {
                id_turma: turmaIdAtual,
                nome_turma: document.getElementById('edit_nome_turma')?.value || '',
                cod_turma: document.getElementById('edit_cod_turma')?.value || '',
                carga_horaria: parseInt(document.getElementById('edit_carga_horaria')?.value || '0'),
                turno: document.getElementById('edit_turno')?.value || '',
                data_recalculo: document.getElementById('edit_data_recalculo')?.value || '',
                id_professor: document.getElementById('edit_id_professor')?.value || '',
                id_sala: document.getElementById('edit_id_sala')?.value || '',
                dias_semana: Array.from(document.querySelectorAll('#modalEditar input[name="dias_semana[]"]:checked')).map(cb => cb.value)
            };
            
            console.log("📊 Dados para preview:", dados);
            
            // Validações básicas
            const erros = [];
            
            if (!dados.carga_horaria || dados.carga_horaria <= 0) {
                erros.push('Carga horária deve ser maior que 0');
            }
            
            if (!dados.turno) {
                erros.push('Selecione um turno');
            }
            
            if (!dados.data_recalculo) {
                erros.push('Informe a data de recálculo');
            }
            
            if (dados.dias_semana.length === 0) {
                erros.push('Selecione pelo menos um dia da semana');
            }
            
            if (!dados.id_sala) {
                erros.push('Selecione uma sala');
            }
            
            if (erros.length > 0) {
                previewDiv.innerHTML = `<div style="color:#b00;padding:15px;background:#ffe6e6;border-radius:8px;border-left:4px solid #b00;">
                    <strong style="display:block;margin-bottom:10px;">⚠️ Correções necessárias:</strong>
                    <ul style="margin:0;padding-left:20px;">
                        ${erros.map(erro => `<li>${erro}</li>`).join('')}
                    </ul>
                </div>`;
                return;
            }
            
            // Calcular informações básicas
            const horasPorEncontro = (dados.turno === 'noite') ? 3 : 4;
            const totalEncontros = Math.ceil(dados.carga_horaria / horasPorEncontro);
            const horasUltimo = dados.carga_horaria - ((totalEncontros - 1) * horasPorEncontro);
            
            // Gerar datas hipotéticas para verificar conflitos
            const datasGeradas = await gerarDatasParaPreview(dados);
            
            if (datasGeradas.length === 0) {
                previewDiv.innerHTML = `<div style="color:#b00;padding:15px;background:#ffe6e6;border-radius:8px;">
                    <strong>⚠️ Não foi possível gerar datas</strong><br>
                    Verifique a data de recálculo e os dias selecionados.
                </div>`;
                return;
            }
            
            // Verificar conflitos
            const conflitos = await verificarConflitos(dados, datasGeradas);
            const temConflitos = (conflitos.conflitos_professor?.length > 0) || 
                                (conflitos.conflitos_sala?.length > 0);
            
            // Montar preview HTML
            let html = `<div class="preview-container" style="padding:0;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,0.1);">
                <div style="background:#2b7;color:white;padding:12px 15px;">
                    <h4 style="margin:0;font-size:16px;display:flex;align-items:center;gap:8px;">
                        <span>📋</span> Pré-visualização do Recálculo
                    </h4>
                </div>
                
                <div style="padding:15px;">
                    <!-- Informações Básicas -->
                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:12px;margin-bottom:20px;padding:15px;background:#f8f9fa;border-radius:6px;">
                        <div><strong style="color:#555;font-size:12px;">🔤 TURMA</strong><br>${dados.nome_turma || '—'}</div>
                        <div><strong style="color:#555;font-size:12px;">🏷️ CÓDIGO</strong><br>${dados.cod_turma || '—'}</div>
                        <div><strong style="color:#555;font-size:12px;">⏱️ CARGA HORÁRIA</strong><br>${dados.carga_horaria}h</div>
                        <div><strong style="color:#555;font-size:12px;">🌅 TURNO</strong><br>${dados.turno.charAt(0).toUpperCase() + dados.turno.slice(1)}</div>
                    </div>
                    
                    <!-- Dias e Local -->
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:20px;">
                        <div style="padding:12px;background:#e8f4f8;border-radius:6px;">
                            <strong style="display:block;margin-bottom:8px;color:#2b7;">🗓️ DIAS DA SEMANA</strong>
                            <div>${dados.dias_semana.map(formatarNomeDia).join('<br>')}</div>
                        </div>
                        <div style="padding:12px;background:#f0f8f0;border-radius:6px;">
                            <strong style="display:block;margin-bottom:8px;color:#2b7;">📍 LOCALIZAÇÃO</strong>
                            <div>🏫 Sala: ${dados.id_sala || "Não selecionada"}</div>
                        </div>
                    </div>
                    
                    <!-- Detalhes das Horas -->
                    <div style="padding:12px;background:#fff8e6;border-radius:6px;margin-bottom:20px;border-left:4px solid #e67e22;">
                        <strong style="display:block;margin-bottom:8px;color:#e67e22;">⏰ DETALHES DAS HORAS</strong>
                        <div style="display:grid;grid-template-columns:repeat(3, 1fr);gap:10px;text-align:center;">
                            <div>
                                <div style="font-size:24px;font-weight:bold;color:#2b7;">${horasPorEncontro}</div>
                                <small>h/encontro</small>
                            </div>
                            <div>
                                <div style="font-size:24px;font-weight:bold;color:#2b7;">${totalEncontros}</div>
                                <small>encontros</small>
                            </div>
                            <div>
                                <div style="font-size:24px;font-weight:bold;color:#2b7;">${horasUltimo}</div>
                                <small>h último</small>
                            </div>
                        </div>
                    </div>`;
            
            // Seção de conflitos
            if (temConflitos) {
                html += `<div style="color:#b00;padding:15px;background:#ffe6e6;border-radius:6px;margin-bottom:20px;border-left:4px solid #b00;">
                    <strong style="display:block;margin-bottom:10px;font-size:14px;">⚠️ CONFLITOS DETECTADOS!</strong>`;
                
                if (conflitos.conflitos_professor?.length > 0) {
                    html += `<div style="margin-bottom:10px;padding:8px;background:#ffcccc;border-radius:4px;">
                        <span style="display:inline-block;width:24px;">👨‍🏫</span>
                        <strong>Professor ocupado em ${conflitos.conflitos_professor.length} data(s)</strong>
                        <div style="margin-top:5px;margin-left:28px;font-size:12px;color:#900;">
                            ${conflitos.conflitos_professor.slice(0, 3).map(d => `• ${d}`).join('<br>')}
                            ${conflitos.conflitos_professor.length > 3 ? `<br>• ... +${conflitos.conflitos_professor.length - 3} datas` : ''}
                        </div>
                    </div>`;
                }
                
                if (conflitos.conflitos_sala?.length > 0) {
                    html += `<div style="margin-bottom:10px;padding:8px;background:#ffcccc;border-radius:4px;">
                        <span style="display:inline-block;width:24px;">🏫</span>
                        <strong>Sala ocupada em ${conflitos.conflitos_sala.length} data(s)</strong>
                        <div style="margin-top:5px;margin-left:28px;font-size:12px;color:#900;">
                            ${conflitos.conflitos_sala.slice(0, 3).map(d => `• ${d}`).join('<br>')}
                            ${conflitos.conflitos_sala.length > 3 ? `<br>• ... +${conflitos.conflitos_sala.length - 3} datas` : ''}
                        </div>
                    </div>`;
                }
                
                html += `<div style="margin-top:10px;padding:10px;background:#ffdddd;border-radius:4px;font-size:12px;">
                    <strong>❗ Importante:</strong> Os encontros nestas datas <strong>NÃO</strong> serão recriados.
                </div>`;
                
                html += `</div>`;
            } else {
                html += `<div style="color:#070;padding:15px;background:#e6ffe6;border-radius:6px;margin-bottom:20px;border-left:4px solid #070;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <span style="font-size:20px;">✅</span>
                        <div>
                            <strong>Sem conflitos detectados!</strong><br>
                            <small>Os encontros podem ser recriados normalmente.</small>
                        </div>
                    </div>
                </div>`;
            }
            
            // Informações adicionais
            const primeiraData = datasGeradas[0];
            const ultimaData = datasGeradas[datasGeradas.length - 1];
            const semanasEstimadas = Math.ceil(totalEncontros / dados.dias_semana.length);
            const hoje = new Date();
            const dataRecalculo = new Date(dados.data_recalculo);
            const diasParaRecalculo = Math.ceil((dataRecalculo - hoje) / (1000 * 60 * 60 * 24));
            
            html += `<div style="padding:12px;background:#e6f3ff;border-radius:6px;font-size:13px;border-left:4px solid #2b7;">
                <strong style="display:block;margin-bottom:8px;color:#2b7;">📅 CRONOGRAMA ESTIMADO</strong>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    <div>
                        <small style="color:#666;">Primeiro encontro</small><br>
                        <strong>${primeiraData || '—'}</strong>
                    </div>
                    <div>
                        <small style="color:#666;">Último encontro</small><br>
                        <strong>${ultimaData || '—'}</strong>
                    </div>
                    <div>
                        <small style="color:#666;">Total de semanas</small><br>
                        <strong>${semanasEstimadas}</strong>
                    </div>
                    <div>
                        <small style="color:#666;">Dias para recálculo</small><br>
                        <strong>${diasParaRecalculo >= 0 ? `${diasParaRecalculo} dias` : 'Já passou'}</strong>
                    </div>
                </div>
            </div>`;
            
            // Resumo final
            html += `<div style="margin-top:15px;padding:12px;background:#f0f0f0;border-radius:6px;text-align:center;font-size:12px;color:#666;">
                <strong>Recálculo a partir de:</strong> ${dados.data_recalculo}<br>
                <small>Serão afetados os encontros a partir desta data</small>
            </div>`;
            
            html += `</div></div>`;
            
            previewDiv.innerHTML = html;
            
        } catch (error) {
            console.error("❌ Erro no preview:", error);
            previewDiv.innerHTML = `<div style="color:#b00;padding:15px;background:#ffe6e6;border-radius:8px;">
                <strong>❌ Erro ao gerar preview</strong><br>
                <small style="color:#900;">${error.message || 'Erro desconhecido'}</small>
            </div>`;
        }
    }

    /**
     * Gera datas para o preview
     */
    async function gerarDatasParaPreview(dados) {
        const horasPorEncontro = (dados.turno === 'noite') ? 3 : 4;
        const totalEncontros = Math.ceil(dados.carga_horaria / horasPorEncontro);
        
        const datasGeradas = [];
        const diasSet = new Set(dados.dias_semana.map(d => diasMap[d]));
        const dataAtual = new Date(dados.data_recalculo + 'T12:00:00'); // Meio-dia para evitar problemas de fuso
        let count = 0;
        const maxTentativas = 365 * 3; 
        
        while (datasGeradas.length < totalEncontros && count < maxTentativas) {
            const diaSemana = dataAtual.getDay(); // 0=domingo, 1=segunda, ..., 6=sábado
            
            // Converter para nosso sistema (1=segunda, ..., 5=sexta)
            let diaCorrigido = diaSemana === 0 ? 7 : diaSemana; // Domingo vira 7
            
            // Verificar se é um dia selecionado (1-5 = segunda a sexta)
            if (diaCorrigido >= 1 && diaCorrigido <= 5) {
                const bitMask = 1 << (diaCorrigido - 1); // Converte para bitmask
                if (diasSet.has(bitMask)) {
                    datasGeradas.push(dataAtual.toISOString().slice(0, 10));
                }
            }
            
            dataAtual.setDate(dataAtual.getDate() + 1);
            count++;
        }
        
        return datasGeradas;
    }

    /**
     * Verifica conflitos com outras turmas
     */
    async function verificarConflitos(dados, datas) {
        try {
            console.log("🔍 Verificando conflitos para", datas.length, "datas");
            
            const response = await fetch('../PHP/verificar_conflitos_edicao.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    id_turma: dados.id_turma,
                    id_sala: dados.id_sala,
                    id_professor: dados.id_professor,
                    turno: dados.turno,
datas: datas
                })
            });
            
            if (!response.ok) {
                throw new Error(`Erro HTTP ${response.status}: ${await response.text()}`);
            }
            
            const result = await response.json();
            
            if (!result.ok) {
                throw new Error(result.error || 'Erro desconhecido na verificação');
            }
            
            return result;
            
        } catch (error) {
            console.error("❌ Erro ao verificar conflitos:", error);
            // Retorna objeto vazio para continuar o fluxo
            return {
                ok: true,
                conflitos_professor: [],
                conflitos_sala: []
            };
        }
    }

    // ========== VALIDAÇÃO E SUBMIT ==========

    /**
     * Valida o formulário antes de enviar
     */
    function validarFormularioEditar() {
        const camposObrigatorios = [
            { id: 'edit_nome_turma', nome: 'Nome da Turma' },
            { id: 'edit_cod_turma', nome: 'Código da Turma' },
            { id: 'edit_carga_horaria', nome: 'Carga Horária' },
            { id: 'edit_turno', nome: 'Turno' },
            { id: 'edit_data_recalculo', nome: 'Data de Recálculo' }
        ];
        
        let camposFaltando = [];
        let erros = [];
        
        // Verificar campos obrigatórios
        camposObrigatorios.forEach(campo => {
            const elemento = document.getElementById(campo.id);
            if (!elemento || !elemento.value.trim()) {
                camposFaltando.push(campo.nome);
            }
        });
        
        // Verificar carga horária válida
        const cargaInput = document.getElementById('edit_carga_horaria');
        if (cargaInput) {
            const valor = parseInt(cargaInput.value);
            if (isNaN(valor) || valor <= 0) {
                erros.push('A carga horária deve ser um número maior que 0');
            }
        }
        
        // Verificar dias da semana
        const diasSelecionados = document.querySelectorAll('#modalEditar input[name="dias_semana[]"]:checked').length;
        if (diasSelecionados === 0) {
            erros.push('Selecione pelo menos um dia da semana');
        }
        // Verificar sala
        const idSala = document.getElementById('edit_id_sala')?.value;
        if (!idSala) {
            erros.push('Selecione uma sala');
        }
        // Verificar data de recálculo válida
        const dataInput = document.getElementById('edit_data_recalculo');
        if (dataInput && dataInput.value) {
            const dataRecalculo = new Date(dataInput.value);
            const hoje = new Date();
            hoje.setHours(0, 0, 0, 0);
            
            if (dataRecalculo < hoje) {
                if (!confirm('⚠️ A data de recálculo é anterior a hoje.\nIsso pode afetar encontros já realizados.\n\nDeseja continuar mesmo assim?')) {
                    return false;
                }
            }
        }
        
        // Se houver erros, mostrar todos
        if (camposFaltando.length > 0 || erros.length > 0) {
            let mensagem = 'Por favor, corrija os seguintes erros:\n\n';
            
            if (camposFaltando.length > 0) {
                mensagem += '• Campos obrigatórios faltando:\n';
                camposFaltando.forEach(campo => {
                    mensagem += `  - ${campo}\n`;
                });
                mensagem += '\n';
            }
            
            if (erros.length > 0) {
                mensagem += '• Erros de validação:\n';
                erros.forEach(erro => {
                    mensagem += `  - ${erro}\n`;
                });
            }
            
            alert(mensagem);
            return false;
        }
        
        return true;
    }

    /**
     * Verifica conflitos antes de enviar
     */
    async function verificarConflitosAntesDeEnviar() {
        try {
            const previewDiv = document.getElementById('previewEditar');
            if (previewDiv) {
                previewDiv.innerHTML = '<div style="padding:20px;text-align:center;color:#666;">🔍 Verificando conflitos finais...</div>';
            }
            
            const dados = {
                id_turma: turmaIdAtual,
                id_sala: document.getElementById('edit_id_sala')?.value || '',
                id_professor: document.getElementById('edit_id_professor')?.value || '',
                turno: document.getElementById('edit_turno')?.value || '',
                data_recalculo: document.getElementById('edit_data_recalculo')?.value || '',
                carga_horaria: parseInt(document.getElementById('edit_carga_horaria')?.value || '0'),
                dias_semana: Array.from(document.querySelectorAll('#modalEditar input[name="dias_semana[]"]:checked')).map(cb => cb.value)
            };
            
            // Gerar datas que seriam criadas
            const datasGeradas = await gerarDatasParaPreview(dados);
            
            if (datasGeradas.length === 0) {
                return confirm("⚠️ Não foi possível gerar datas com os parâmetros fornecidos.\n\nIsso pode acontecer se:\n• A carga horária for muito baixa\n• Os dias selecionados não ocorrerem\n\nDeseja continuar mesmo assim?");
            }
            
            // Verificar conflitos
            const conflitos = await verificarConflitos(dados, datasGeradas);
            
            if (conflitos.ok) {
                const temConflitos = (conflitos.conflitos_professor?.length > 0) || 
                                    (conflitos.conflitos_sala?.length > 0);
                
                if (temConflitos) {
                    let mensagem = "⚠️ CONFLITOS DETECTADOS!\n\n";
                    
                    if (conflitos.conflitos_professor?.length > 0) {
                        mensagem += `👨‍🏫 Professor ocupado em ${conflitos.conflitos_professor.length} data(s)\n`;
                    }
                    
                    if (conflitos.conflitos_sala?.length > 0) {
                        mensagem += `🏫 Sala ocupada em ${conflitos.conflitos_sala.length} data(s)\n`;
                    }
                    
                    mensagem += "\nOs encontros nestas datas NÃO serão recriados.\n\n";
                    mensagem += "Deseja continuar mesmo assim?";
                    
                    return confirm(mensagem);
                } else {
                    return confirm("✅ Não foram detectados conflitos.\n\nDeseja recriar os encontros futuros?");
                }
            } else {
                throw new Error(conflitos.error || "Erro na verificação de conflitos");
            }
            
        } catch (error) {
            console.error("❌ Erro ao verificar conflitos:", error);
            return confirm("⚠️ Não foi possível verificar conflitos.\n\nErro: " + error.message + "\n\nDeseja continuar mesmo assim?");
        }
    }

    // ========== INICIALIZAÇÃO ==========
    
    /**
     * Inicializa o script quando o DOM estiver carregado
     */
    function inicializar() {
        console.log("🚀 Inicializando editar_turmas.js");
        
        // Configurar todos os event listeners
        configurarEventListeners();
        // Verificar se elementos necessários existem
        if (!document.getElementById('modalEditar')) {
            console.warn("⚠️ Modal de edição não encontrado no DOM");
        }
        
        if (!document.getElementById('formEditarTurma')) {
            console.warn("⚠️ Formulário de edição não encontrado no DOM");
        }
        
        // Adicionar estilos CSS
        const estilos = `
            .preview-container {
                transition: all 0.3s ease;
            }
            .preview-container:hover {
                box-shadow: 0 4px 20px rgba(0,0,0,0.15) !important;
            }
            .check-dias label {
                transition: all 0.2s ease;
                padding: 4px 8px;
                border-radius: 4px;
                cursor: pointer;
            }
            .check-dias label:hover {
                background: rgba(43, 119, 119, 0.1);
            }
            .check-dias input:checked + span {
                font-weight: bold;
                color: #2b7;
            }
        `;
        
        const styleSheet = document.createElement("style");
        styleSheet.textContent = estilos;
        document.head.appendChild(styleSheet);
        
        console.log("✅ Script editar_turmas.js inicializado com sucesso");
    }
    
    // Esperar o DOM carregar completamente
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', inicializar);
    } else {
        inicializar();
    }
    
    // Marcar que o script foi carregado
    window.modalEditarLoaded = true;
    
} else {
    console.log("ℹ️ Script editar_turmas.js já foi carregado anteriormente");
}