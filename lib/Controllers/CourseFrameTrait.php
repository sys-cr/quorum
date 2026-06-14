<?php

declare(strict_types=1);

namespace Quorum\Controllers;

/**
 * Shared course-context frame for the Trails controllers that render inside
 * the course tab (IndexController = course-app home page, WorkplaceController
 * = create/edit/restart forms, when invoked from the course tab with `cid`).
 *
 * Background: Stud.IP draws the course header, tabs and sidebar ONLY while a
 * `/course/…` navigation is active. Without it the page renders as a bare full
 * page in the global header (and the form loses the course context). This
 * trait holds that logic once for both controllers.
 *
 * Expects `$this->plugin` (PluginController property).
 */
trait CourseFrameTrait
{
    /**
     * Forces the course frame: course title + active `/course/quorum` nav item.
     * Both defensive — if `Context`/`Navigation` fail (tab not registered) the
     * page stays functional without the highlight.
     */
    protected function activateCourseFrame(): void
    {
        try {
            \PageLayout::setTitle(\Context::getHeaderLine() . ' – ' . _quorum('Quorum'));
        } catch (\Throwable) {
            \PageLayout::setTitle(_quorum('Quorum'));
        }
        try {
            \Navigation::activateItem('/course/quorum');
        } catch (\InvalidArgumentException) {
            // Tab not registered (plugin not enabled as a course tool).
        }
    }

    /**
     * Course sidebar: active/archive views + actions (new / bind / import),
     * identical to the course-app home page. On form sub-pages pass
     * `$active = 'form'` so no view entry is highlighted.
     *
     * @param string $active 'active' | 'archive' | 'form'
     */
    protected function buildCourseSidebar(string $cid, string $active = 'active'): void
    {
        $sidebar = \Sidebar::Get();
        $sidebar->setTitle(_quorum('Quorum'));

        $views = new \ViewsWidget();
        $views->addLink(
            _quorum('Aktive Umfragen'),
            \PluginEngine::getURL($this->plugin, ['cid' => $cid], 'index/index', true)
        )->setActive($active === 'active');
        $views->addLink(
            _quorum('Sammlungen'),
            \PluginEngine::getURL($this->plugin, ['cid' => $cid, 'view' => 'collections'], 'index/index', true)
        )->setActive($active === 'collections');
        $views->addLink(
            _quorum('Archiv'),
            \PluginEngine::getURL($this->plugin, ['cid' => $cid, 'view' => 'archive'], 'index/index', true)
        )->setActive($active === 'archive');
        $sidebar->addWidget($views);

        $actions = new \ActionsWidget();
        $actions->addLink(
            _quorum('Neue Umfrage anlegen'),
            \PluginEngine::getURL($this->plugin, ['cid' => $cid], 'workplace/new', true),
            \Icon::create('add')
        );
        $actions->addLink(
            _quorum('Neue Sammlung anlegen'),
            \PluginEngine::getURL($this->plugin, ['cid' => $cid], 'workplace/collection_new', true),
            \Icon::create('add')
        );
        $actions->addLink(
            _quorum('Umfrage einbinden …'),
            \PluginEngine::getURL($this->plugin, ['cid' => $cid], 'index/import', true),
            \Icon::create('link-intern')
        );
        $actions->addLink(
            _quorum('Umfrage importieren …'),
            \PluginEngine::getURL($this->plugin, ['cid' => $cid], 'index/import_file', true),
            \Icon::create('upload')
        );
        $sidebar->addWidget($actions);
    }
}
