<?php
/**
 * A RedKiteCms Block
 */

namespace RedKiteCms\Block\MarkdownBlockBundle\Core\Block;

use RedKiteLabs\RedKiteCms\RedKiteCmsBundle\Core\Content\Block\BlockManager;
use RedKiteLabs\RedKiteCms\RedKiteCmsBundle\Core\Content\Validator\ParametersValidatorInterface;
use RedKiteLabs\RedKiteCms\RedKiteCmsBundle\Core\EventsHandler\EventsHandlerInterface;
use RedKiteLabs\RedKiteCms\RedKiteCmsBundle\Core\Repository\Factory\FactoryRepositoryInterface;
use RedKiteLabs\RedKiteCms\RedKiteCmsBundle\Core\Translator\TranslatorInterface;

/**
 * Description of BlockManagerMarkdownBlock
 */
class BlockManagerMarkdownBlock extends BlockManager
{
    /** @var null|TranslatorInterface */
    protected $translator;

    /**
     * Constructor
     *
     * @param null|EventsHandlerInterface       $eventsHandler
     * @param null|FactoryRepositoryInterface   $factoryRepository
     * @param null|ParametersValidatorInterface $validator
     * @param null|TranslatorInterface          $translator
     */
    public function __construct(EventsHandlerInterface $eventsHandler = null, FactoryRepositoryInterface $factoryRepository = null, ParametersValidatorInterface $validator = null, TranslatorInterface $translator = null)
    {
        parent::__construct($eventsHandler, $factoryRepository, $validator);

        $this->translator = $translator;
    }

    /**
     * @inheritdoc
     */
    public function getDefaultValue()
    {
        $message = "This is the default content for a new markdown block";
        if (null !== $this->translator) {
            $message = $this->translator->translate($message);
        }

        return array(
            'Content' => $message,
        );
    }

    /**
     * @inheritdoc
     */
    protected function renderHtml()
    {
        return array('RenderView' => array(
            'view' => 'MarkdownBlockBundle:Content:markdownblock.html.twig',
            'options' => array(
                'block_id' => $this->alBlock->getId(),
                'block_content' => $this->alBlock->getContent(),
            ),
        ));
    }
}
