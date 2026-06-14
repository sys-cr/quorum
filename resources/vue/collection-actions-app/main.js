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
// Value converters per attribute type — encapsulate the operators (`??`/`||`/
// `===`) so `readMountProps` itself has no branches.
const str  = (v) => v ?? ''
const opt  = (v) => v || null
const flag = (v) => v === '1'

// Bundles reading the data attributes into the Vue props. Extracted so `mount`
// stays flat.
const readMountProps = (root) => {
    const d = root.dataset
    return {
        name:         str(d.name),
        collectionId: str(d.collectionId),
        pluginUrl:    str(d.pluginUrl),
        csrf:         str(d.csrf),
        archived:     flag(d.archived),
        anyActive:    flag(d.anyActive),
        hasPolls:     flag(d.hasPolls),
        presenterUrl: str(d.presenterUrl),
        editUrl:      str(d.editUrl),
        newPollUrl:   str(d.newPollUrl),
        downloadUrl:  str(d.downloadUrl),
        backUrl:      str(d.backUrl),
        qrUrl:        opt(d.qrUrl),
        qrShort:      opt(d.qrShort),
        qrTitle:      str(d.qrTitle),
    }
}

const mount = (root) => {
    const lang = (globalThis.STUDIP?.LANGUAGE_BASE ?? 'de').slice(0, 2)
    const app  = createApp(CollectionActions, readMountProps(root))
    app.use(createQuorumI18n(lang))
    app.mount(root)
    return app
}

if (typeof document !== 'undefined') {
    const root = document.getElementById('quorum-collection-actions')
    if (root) mount(root)
}
