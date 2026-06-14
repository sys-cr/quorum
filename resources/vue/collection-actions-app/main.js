import { createApp } from 'vue'
import CollectionActions from './CollectionActions.vue'
import { createQuorumI18n } from '../i18n.js'

/**
 * Bootstrap of the collection action menu on the server-rendered detail page
 * (`views/workplace/collection.php`). It has its own Vite entry because the
 * page does not load a Vue app bundle — it only needs this menu plus the
 * shared dialogs (QrCodeDialog/QuorumDialog). Only vue-i18n, no Pinia. All
 * states/URLs are provided server-side via data attributes of the mount.
 */
const mount = (root) => {
    const lang = (globalThis.STUDIP?.LANGUAGE_BASE ?? 'de').slice(0, 2)
    const d    = root.dataset
    const app  = createApp(CollectionActions, {
        name:         d.name ?? '',
        collectionId: d.collectionId ?? '',
        pluginUrl:    d.pluginUrl ?? '',
        csrf:         d.csrf ?? '',
        archived:     d.archived === '1',
        anyActive:    d.anyActive === '1',
        hasPolls:     d.hasPolls === '1',
        presenterUrl: d.presenterUrl ?? '',
        editUrl:      d.editUrl ?? '',
        newPollUrl:   d.newPollUrl ?? '',
        downloadUrl:  d.downloadUrl ?? '',
        backUrl:      d.backUrl ?? '',
        qrUrl:        d.qrUrl || null,
        qrShort:      d.qrShort || null,
        qrTitle:      d.qrTitle ?? '',
    })
    app.use(createQuorumI18n(lang))
    app.mount(root)
    return app
}

if (typeof document !== 'undefined') {
    const root = document.getElementById('quorum-collection-actions')
    if (root) mount(root)
}
