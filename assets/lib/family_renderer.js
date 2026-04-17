export function renderFamilyList(families, selectedId) {
    return families.map((family) => renderFamilyCard(family, selectedId)).join('');
}

function renderFamilyCard(family, selectedId) {
    const active = family.id == selectedId ? 'active' : '';
    return `<a href="#" class="list-group-item ${active}"
                data-family-id="${family.id}"
                data-family-nom="${family.nom}"
                data-action="click->family-search#selectFamily">
        <h4 style="margin:0 0 5px 0;">Famille ${family.nom}</h4>
        ${renderGeniteurs(family.geniteurs)}
        ${renderMembres(family.membres)}
        ${renderAdresse(family.sendingAdresse)}
    </a>`;
}

function renderGeniteurs(geniteurs) {
    if (!geniteurs || geniteurs.length === 0) return '';
    const items = geniteurs.map((g) => `<div>${g.prenom} ${g.nom}</div>`).join('');
    return `<div><strong>Parents/Représentants légaux</strong>${items}</div>`;
}

function renderMembres(membres) {
    if (!membres || membres.length === 0) return '';
    const items = membres.map((m) => {
        const attr = m.activeAttribution ? `: ${m.activeAttribution.representation}` : '';
        return `<div>${m.fullName}${attr}</div>`;
    }).join('');
    return `<div><strong>Membres actuels</strong>${items}</div>`;
}

function renderAdresse(adresse) {
    if (!adresse) return '';
    return `<address style="margin:0;"><strong>Adresse</strong><br/>
        ${adresse.rue}<br/>${adresse.npa} - ${adresse.localite}</address>`;
}
