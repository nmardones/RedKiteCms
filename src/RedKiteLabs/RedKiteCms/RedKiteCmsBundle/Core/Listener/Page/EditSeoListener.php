<?php
/**
 * This file is part of the RedKite CMS Application and it is distributed
 * under the MIT License. To use this application you must leave
 * intact this copyright notice.
 *
 * Copyright (c) RedKite Labs <webmaster@redkite-labs.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * For extra documentation and help please visit http://www.redkite-labs.com
 *
 * @license    MIT License
 *
 */

namespace RedKiteLabs\RedKiteCms\RedKiteCmsBundle\Core\Listener\Page;

use RedKiteLabs\RedKiteCms\RedKiteCmsBundle\Core\Event\Content\Page\BeforeEditPageCommitEvent;
use RedKiteLabs\RedKiteCms\RedKiteCmsBundle\Core\Content\Seo\SeoManager;
use \RedKiteLabs\RedKiteCms\RedKiteCmsBundle\Core\Exception\Content\General;
use RedKiteLabs\RedKiteCms\RedKiteCmsBundle\Core\Exception\General\InvalidArgumentException;

/**
 * Listen to the onBeforeEditPageCommit event to edit the seo attributes when a new
 * page is edited
 *
 * @author RedKite Labs <webmaster@redkite-labs.com>
 *
 * @api
 */
class EditSeoListener
{
    private $seoManager;

    /**
     * Constructor
     *
     * @param SeoManager $seoManager
     *
     * @api
     */
    public function __construct(SeoManager $seoManager)
    {
        $this->seoManager = $seoManager;
    }

    /**
     * Edits the seo attributes when a new page is edited
     *
     * @param  BeforeEditPageCommitEvent $event
     * @return boolean
     * @throws InvalidArgumentException
     * @throws \Exception
     *
     * @api
     */
    public function onBeforeEditPageCommit(BeforeEditPageCommitEvent $event)
    {
        if ($event->isAborted()) {
            return;
        }

        $pageManager = $event->getContentManager();
        $pageRepository = $pageManager->getPageRepository();
        $values = $event->getValues();

        if (!is_array($values)) {
            throw new InvalidArgumentException('exception_invalid_value_array_required');
        }

        try {
            $pageInformation = $pageManager->getTemplateManager()
                    ->getPageBlocks()
                    ->getPageInformation();

            $idLanguage = $pageInformation["idLanguage"];
            $idPage = $pageInformation["idPage"];
            $seoRepository = $this->seoManager->getSeoRepository();
            $seo = $seoRepository->fromPageAndLanguage($idLanguage, $idPage);
            if (!empty($values)) {
                $seoRepository->setConnection($pageRepository->getConnection());
                $pageRepository->startTransaction();
                $this->seoManager->set($seo);
                $values = array_merge($values, array('PageId' => $idPage, 'LanguageId' => $idLanguage));
                $result = $this->seoManager->save($values);

                if (false !== $result) {
                    $pageRepository->commit();

                    return;
                }

                $pageRepository->rollBack();
                $event->abort();
            }
        } catch (\Exception $e) {
            $event->abort();

            if (isset($pageRepository) && $pageRepository !== null) {
                $pageRepository->rollBack();
            }

            throw $e;
        }
    }
}
