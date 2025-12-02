/**
 * LOGIN UNIQUEMENT
 * Retourne: { success: true/false, status: "...", message: "..." }
 */
(async function () {
    const S = {
        log: (msg) => console.log('[LOGIN]', msg),
        error: (msg) => console.error('[LOGIN]', msg)
    };

    const wait = (ms) => new Promise(resolve => setTimeout(resolve, ms));

    S.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    S.log('🔐 LOGIN ANNONCES.NC');
    S.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

    // Charger config
    const configStr = localStorage.getItem('SCRAPER_CONFIG');
    if (!configStr) {
        const error = 'Config SCRAPER_CONFIG manquante';
        S.error('❌ ' + error);
        return { success: false, status: 'error', message: error };
    }

    let config;
    try {
        config = JSON.parse(configStr);
    } catch (e) {
        const error = 'Config JSON invalide';
        S.error('❌ ' + error);
        return { success: false, status: 'error', message: error };
    }

    if (!config.email || !config.password) {
        const error = 'Credentials manquants dans config';
        S.error('❌ ' + error);
        return { success: false, status: 'error', message: error };
    }

    S.log('Config OK');
    S.log('Email: ' + config.email);

    // Attendre chargement modal
    S.log('⏳ Attente modal (' + config.timeouts.modal + 'ms)...');
    await wait(config.timeouts.modal);

    // Chercher modal
    S.log('🔍 Recherche modal: ' + config.selectors.loginModal);
    const modal = document.querySelector(config.selectors.loginModal);

    if (!modal) {
        S.log('✅ Pas de modal = Déjà connecté');
        S.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        return { success: true, status: 'already_logged_in', message: 'Déjà connecté' };
    }

    S.log('⚠️  Modal détecté, login en cours...');

    // Trouver champs
    const emailInput = modal.querySelector(config.selectors.loginEmail);
    const passwordInput = modal.querySelector(config.selectors.loginPassword);
    const submitBtn = modal.querySelector(config.selectors.loginSubmit);

    if (!emailInput) {
        const error = 'Champ email introuvable';
        S.error('❌ ' + error);
        return { success: false, status: 'error', message: error };
    }

    if (!passwordInput) {
        const error = 'Champ password introuvable';
        S.error('❌ ' + error);
        return { success: false, status: 'error', message: error };
    }

    if (!submitBtn) {
        const error = 'Bouton submit introuvable';
        S.error('❌ ' + error);
        return { success: false, status: 'error', message: error };
    }

    // Remplir formulaire
    S.log('📝 Remplissage formulaire...');

    emailInput.value = config.email;
    emailInput.dispatchEvent(new Event('input', { bubbles: true }));
    emailInput.dispatchEvent(new Event('change', { bubbles: true }));

    await wait(config.timeouts.input);

    passwordInput.value = config.password;
    passwordInput.dispatchEvent(new Event('input', { bubbles: true }));
    passwordInput.dispatchEvent(new Event('change', { bubbles: true }));

    await wait(config.timeouts.submit);

    // Activer et cliquer bouton
    submitBtn.disabled = false;
    submitBtn.removeAttribute('disabled');

    S.log('👆 Clic submit...');
    submitBtn.click();

    // Attendre disparition modal
    S.log('⏳ Attente disparition modal (' + config.timeouts.loginSuccess + 'ms)...');
    await wait(config.timeouts.loginSuccess);

    const stillThere = document.querySelector(config.selectors.loginModal);

    if (stillThere) {
        const error = 'Modal toujours visible après ' + config.timeouts.loginSuccess + 'ms';
        S.error('❌ ' + error);
        S.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        return { success: false, status: 'login_failed', message: error };
    }

    S.log('✅✅✅ Login réussi !');
    S.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

    return { success: true, status: 'logged_in', message: 'Login réussi' };

})();