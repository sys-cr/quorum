import { createMemoryHistory, createRouter, createWebHashHistory } from 'vue-router'
import SurveysIndex from './components/SurveysIndex.vue'
import CollectionsIndex from './components/CollectionsIndex.vue'
import VotingCompare   from './components/VotingCompare.vue'
import Archive         from './components/Archive.vue'

/**
 * Vue Router for the teacher course-app.
 *
 * Hash routing is used because Stud.IP serves the PHP route
 * `/plugins.php/quorumstudipplugin/index/index?cid=…` as a container; in-plugin
 * route changes only work via hash without Stud.IP throwing a 404.
 *
 * `createMemoryHistory` is used in test/SSR contexts.
 */

export const routes = [
    { path: '/',                redirect: '/surveys' },
    { path: '/surveys',     name: 'surveys', component: SurveysIndex },
    { path: '/surveys/:id', name: 'survey',  component: SurveysIndex, props: true },
    { path: '/collections', name: 'collections', component: CollectionsIndex },
    { path: '/compare/:a/:b',   name: 'compare',     component: VotingCompare,
      props: route => ({ idA: route.params.a, idB: route.params.b }) },
    { path: '/archive',         name: 'archive',     component: Archive },
]

export const createQuorumRouter = ({ history } = {}) => createRouter({
    history: history ?? (typeof window === 'undefined' ? createMemoryHistory() : createWebHashHistory()),
    routes,
})
