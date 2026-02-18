(function () {
  const ticketList = document.getElementById('ticketList');
  if (!ticketList) return;

  const textInput = document.getElementById('filterText');
  const statusSelect = document.getElementById('filterStatus');
  const prioritySelect = document.getElementById('filterPriority');
  const assistantContent = document.getElementById('assistantContent');

  const templates = [
    'Hola, estamos revisando tu caso. Te compartiré una actualización en breve.',
    'Necesitamos validar algunos datos adicionales. Por favor confirma si el error persiste y adjunta evidencia.',
    'Realizamos un ajuste inicial. Por favor prueba nuevamente y confirma si el servicio quedó operativo.',
  ];

  function applyFilters() {
    const term = (textInput?.value || '').toLowerCase().trim();
    const status = statusSelect?.value || '';
    const priority = prioritySelect?.value || '';

    ticketList.querySelectorAll('.ticket-item').forEach((item) => {
      const matchTerm = !term
        || item.dataset.title.includes(term)
        || item.dataset.area.includes(term)
        || item.dataset.requester.includes(term)
        || item.dataset.description.includes(term);
      const matchStatus = !status || item.dataset.status === status;
      const matchPriority = !priority || item.dataset.priority === priority;
      item.style.display = matchTerm && matchStatus && matchPriority ? '' : 'none';
    });
  }

  function getRecommendations(content) {
    const text = content.toLowerCase();

    if (text.includes('contraseña') || text.includes('acceso') || text.includes('usuario')) {
      return [
        'Verificar bloqueo de cuenta en AD/LDAP o sistema de identidad.',
        'Forzar reseteo de contraseña temporal y exigir cambio en primer ingreso.',
        'Confirmar rol/permisos y trazabilidad de intentos fallidos.',
      ];
    }

    if (text.includes('impresora') || text.includes('hardware') || text.includes('equipo')) {
      return [
        'Validar conectividad física y estado del dispositivo.',
        'Reinstalar/controlar drivers y cola de impresión local.',
        'Confirmar si requiere visita en sitio y escalar a soporte de campo.',
      ];
    }

    if (text.includes('red') || text.includes('internet') || text.includes('vpn') || text.includes('conex')) {
      return [
        'Revisar estado de enlace, VPN y segmentación de red del área impactada.',
        'Comprobar DNS/DHCP y latencia desde equipo afectado.',
        'Definir workaround temporal y comunicar ETA al usuario.',
      ];
    }

    if (text.includes('sistema') || text.includes('error') || text.includes('aplicaci')) {
      return [
        'Reproducir el error y capturar evidencia (logs, hora, usuario, módulo).',
        'Validar cambios recientes en despliegue/configuración.',
        'Aplicar corrección incremental y monitorear durante 24h.',
      ];
    }

    return [
      'Clasificar impacto: usuario único, área completa o empresa.',
      'Definir hipótesis rápida y ejecutar prueba de menor riesgo.',
      'Documentar hallazgos y próxima actualización al solicitante.',
    ];
  }

  function renderAssistant(ticketItem) {
    const title = ticketItem.querySelector('h3')?.textContent || 'Caso';
    const description = ticketItem.dataset.description || '';
    const recommendations = getRecommendations(description);

    assistantContent.innerHTML = `
      <h4>${title}</h4>
      <p><strong>Recomendaciones automáticas</strong></p>
      <ul>
        ${recommendations.map((item) => `<li>${item}</li>`).join('')}
      </ul>
      <p><strong>Próximo paso recomendado:</strong> Actualiza estado a "en progreso" y define fecha planificada para cumplir SLA.</p>
    `;
  }

  ticketList.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;

    const ticket = target.closest('.ticket-item');
    if (!ticket) return;

    if (target.classList.contains('suggest-btn')) {
      renderAssistant(ticket);
    }

    if (target.classList.contains('template-btn')) {
      const textarea = ticket.querySelector('.response-form textarea[name="message"]');
      if (textarea) {
        const randomTemplate = templates[Math.floor(Math.random() * templates.length)];
        textarea.value = randomTemplate;
        textarea.focus();
      }
    }
  });

  [textInput, statusSelect, prioritySelect].forEach((el) => {
    el?.addEventListener('input', applyFilters);
    el?.addEventListener('change', applyFilters);
  });
})();
