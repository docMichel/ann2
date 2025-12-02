/**
 * SCRAPER - Assume déjà connecté
 */
(async function () {
    'use strict';

    // ========== LOGGER ==========
    const S = {
        log: (msg) => console.log('[SCRAPER]', msg),
        error: (msg) => console.error('[SCRAPER]', msg),
        warn: (msg) => console.warn('[SCRAPER]', msg)
    };

    const wait = (ms) => new Promise(resolve => setTimeout(resolve, ms));

    S.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    S.log('🚀 SCRAPER ANNONCES.NC');
    S.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

    // ========== CHARGER CONFIG ==========
    const configStr = localStorage.getItem('SCRAPER_CONFIG');
    if (!configStr) {
        S.error('❌ Config SCRAPER_CONFIG manquante');
        return { success: false, error: 'no_config' };
    }

    let CONFIG;
    try {
        CONFIG = JSON.parse(configStr);
    } catch (e) {
        S.error('❌ Config JSON invalide');
        return { success: false, error: 'invalid_config' };
    }

    S.log('✅ Config chargée');
    S.log('API: ' + CONFIG.apiUrl);
    S.log('Max pages: ' + CONFIG.maxPages);
    S.log('Max conversations: ' + CONFIG.maxConversations);
    S.log('');

    // ========== VÉRIFIER QU'ON A DES CONVERSATIONS ==========
    S.log('🔍 Vérification présence conversations...');
    S.log('   Sélecteur: ' + CONFIG.selectors.convList);

    await wait(2000); // Attendre que la page soit stable

    let initialConvs = document.querySelectorAll(CONFIG.selectors.convList);

    // Si pas de conversations, attendre un peu plus (max 10s)
    let attempts = 0;
    while (initialConvs.length === 0 && attempts < 50) {
        await wait(200);
        initialConvs = document.querySelectorAll(CONFIG.selectors.convList);
        attempts++;
    }

    if (initialConvs.length === 0) {
        S.error('❌ Aucune conversation trouvée !');
        S.error('   URL actuelle: ' + window.location.href);
        S.error('   Sélecteur utilisé: ' + CONFIG.selectors.convList);
        S.error('   Êtes-vous bien connecté et sur /dashboard/conversations ?');
        return { success: false, error: 'no_conversations' };
    }

    S.log('✅ ' + initialConvs.length + ' conversations détectées');
    S.log('');

    // ========== INTERCEPTEUR XHR ==========
    const xhrData = { conversationId: null, messages: null };

    const originalSend = XMLHttpRequest.prototype.send;
    XMLHttpRequest.prototype.send = function () {
        const xhr = this;
        xhr.addEventListener('readystatechange', function () {
            if (xhr.readyState === 4 && xhr.status === 200) {
                const url = xhr._url || '';
                if (url.includes('/conversations/') && url.includes('/messages')) {
                    try {
                        const response = JSON.parse(xhr.responseText || xhr.response);
                        xhrData.messages = response;
                    } catch (e) {
                        S.error('Erreur parse XHR: ' + e.message);
                    }
                }
            }
        });
        return originalSend.apply(this, arguments);
    };

    const originalOpen = XMLHttpRequest.prototype.open;
    XMLHttpRequest.prototype.open = function (method, url) {
        this._url = url;
        const convMatch = url?.match(/\/conversations\/(\d+)/);
        if (convMatch) {
            xhrData.conversationId = convMatch[1];
            xhrData.messages = null;
        }
        return originalOpen.apply(this, arguments);
    };

    // ========== EXTRACTION IMAGES ==========
    function extractImages() {
        const images = [];
        document.querySelectorAll(CONFIG.selectors.images).forEach(img => {
            const src = img.getAttribute('src');
            if (src) {
                images.push({
                    thumbnail: src,
                    full: src.replace('/tiny_', '/')
                });
            }
        });
        return images;
    }

    // ========== EXTRACTION ANNONCE ==========
    async function getAnnonceData() {
        const btn = document.querySelector(CONFIG.selectors.annonceBtn);
        if (!btn) return null;

        btn.click();
        await wait(CONFIG.timeouts.annonceModal);

        const descElement = document.querySelector(CONFIG.selectors.annonceDesc);
        const description = descElement?.textContent.trim() || '';

        const badgeElement = document.querySelector(CONFIG.selectors.annonceBadge);
        const badgeText = badgeElement?.textContent.trim() || '';
        const annonceIdMatch = badgeText.match(/Annonce (\d+)/);
        const annonceId = annonceIdMatch ? annonceIdMatch[1] : null;

        const closeBtn = document.querySelector(CONFIG.selectors.annonceClose);
        if (closeBtn) {
            closeBtn.click();
            await wait(500);
        }

        return { id: annonceId, description: description };
    }

    // ========== API ==========
    async function sendToAPI(data) {
        try {
            const response = await fetch(CONFIG.apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            const text = await response.text();
            try {
                return JSON.parse(text);
            } catch (e) {
                return { raw: text };
            }
        } catch (error) {
            S.error('Erreur API: ' + error);
            return null;
        }
    }

    // ========== PAGINATION ==========
    async function loadMoreConversations(pagesLoaded) {
        const btnVoirPlus = document.querySelector(CONFIG.selectors.voirPlus);
        if (!btnVoirPlus || btnVoirPlus.textContent.trim() !== 'Voir plus') {
            return false;
        }

        S.log('📄 Chargement page ' + (pagesLoaded + 1) + '/' + CONFIG.maxPages);
        btnVoirPlus.click();
        await wait(CONFIG.timeouts.loadMore);
        return true;
    }

    // ========== CHARGEMENT PAGES ==========
    S.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    S.log('📋 CHARGEMENT CONVERSATIONS');
    S.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

    const results = [];
    let pagesLoaded = 0;

    while (pagesLoaded < CONFIG.maxPages) {
        const currentCount = document.querySelectorAll(CONFIG.selectors.convList).length;
        S.log('Conversations chargées: ' + currentCount);

        if (currentCount >= CONFIG.maxConversations) {
            S.log('✅ Limite atteinte (' + CONFIG.maxConversations + ')');
            break;
        }

        const hasMore = await loadMoreConversations(pagesLoaded);
        if (!hasMore) {
            S.log('ℹ️  Plus de bouton "Voir plus"');
            break;
        }
        pagesLoaded++;
    }

    S.log('');
    S.log('✅ ' + pagesLoaded + ' pages chargées');
    S.log('');

    const conversations = document.querySelectorAll(CONFIG.selectors.convList);
    const totalToProcess = Math.min(conversations.length, CONFIG.maxConversations);

    S.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    S.log('💬 TRAITEMENT ' + totalToProcess + ' CONVERSATIONS');
    S.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    S.log('');

    // ========== BOUCLE CONVERSATIONS ==========
    for (let i = 0; i < totalToProcess; i++) {
        try {
            xhrData.conversationId = null;
            xhrData.messages = null;

            const convEl = conversations[i];
            const titleElement = convEl.querySelector(CONFIG.selectors.convTitle);
            const userElement = convEl.querySelector(CONFIG.selectors.convUser);
            const title = titleElement?.textContent.trim() || '';
            const userName = userElement?.textContent.trim() || '';

            const userIdMatch = userName.match(/Utilisateur (\d+)/);
            const userId = userIdMatch ? userIdMatch[1] : null;

            S.log('[' + (i + 1) + '/' + totalToProcess + '] ' + title + ' - ' + userName);

            convEl.click();

            // Attendre XHR
            let attempts = 0;
            const maxAttempts = CONFIG.timeouts.xhrTimeout / 100;
            while (!xhrData.messages && attempts < maxAttempts) {
                await wait(100);
                attempts++;
            }

            if (!xhrData.messages) {
                S.log('   ❌ Timeout XHR');
                S.log('');
                results.push({ success: false, error: 'timeout' });
                continue;
            }

            S.log('   📨 ' + xhrData.messages.length + ' messages');

            // Images
            await wait(CONFIG.timeouts.images);
            const images = extractImages();
            if (images.length > 0) {
                S.log('   📸 ' + images.length + ' images');
            }

            // Annonce
            const annonceData = await getAnnonceData();
            if (annonceData?.id) {
                S.log('   📄 Annonce ' + annonceData.id);
            }

            const payload = {
                conversation_id: xhrData.conversationId,
                user_id: userId,
                info: {
                    title: title,
                    user: userName,
                    site: 'annonces.nc'
                },
                messages: xhrData.messages,
                images: images,
                annonce_id: annonceData?.id,
                annonce_url: annonceData?.id ? 'https://annonces.nc/annonce/' + annonceData.id : null,
                annonce_description: annonceData?.description
            };

            const result = await sendToAPI(payload);

            if (result?.status === 'saved') {
                S.log('   ✅ Sauvegardé');
            } else if (result?.status === 'exists') {
                S.log('   ⏭️  Existe déjà');
            } else {
                S.log('   ❌ Échec API');
            }
            S.log('');

            results.push({ success: !!result, response: result });
            await wait(CONFIG.timeouts.betweenConvs);

        } catch (error) {
            S.error('Erreur: ' + error.message);
            S.log('');
            results.push({ success: false, error: error.message });
        }
    }

    // ========== RÉSUMÉ ==========
    S.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    S.log('✨ TERMINÉ');
    S.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    S.log('Succès: ' + results.filter(r => r.success).length + '/' + totalToProcess);
    S.log('Échecs: ' + results.filter(r => !r.success).length);

    return {
        success: true,
        total: totalToProcess,
        succeeded: results.filter(r => r.success).length,
        failed: results.filter(r => !r.success).length,
        results: results
    };

})();