<?php

namespace Remp\MailerModule\Forms;

use Nette\Application\UI\Form;
use Nette\InvalidStateException;
use Nette\SmartObject;
use Remp\MailerModule\Form\Rendering\MaterialRenderer;
use Remp\MailerModule\Generators\GeneratorFactory;
use Remp\MailerModule\Repository\SourceTemplatesRepository;

class MailGeneratorFormFactory
{
    use SmartObject;

    private $sourceTemplatesRepository;

    private $mailGeneratorFactory;

    public function __construct(
        SourceTemplatesRepository $sourceTemplatesRepository,
        GeneratorFactory $mailGeneratorFactory
    ) {
        $this->sourceTemplatesRepository = $sourceTemplatesRepository;
        $this->mailGeneratorFactory = $mailGeneratorFactory;
    }

    public function create($sourceTemplateId, callable $onSubmit, callable $link = null)
    {
        $form = new Form;
        $form->setRenderer(new MaterialRenderer());
        $form->addProtection();

        $keys = $this->mailGeneratorFactory->keys();
        $pairs = $this->sourceTemplatesRepository->getTable()
            ->where(['generator' => $keys])
            ->fetchPairs('id', 'title');

        $form->addSelect('source_template_id', 'Generator', $pairs)
            ->setRequired("Field 'Generator' is required.");

        $generator = null;
        $template = null;

        if ($sourceTemplateId) {
            $template = $this->sourceTemplatesRepository->find($sourceTemplateId);
            $generator = $template->generator;
        } else {
            foreach ($keys as $key) {
                $tmpl = $this->sourceTemplatesRepository->getTable()->where(['generator' => $key])->fetch();
                if ($tmpl) {
                    $template = $tmpl;
                    $generator = $key;
                    break;
                }
            }
        }

        if ($generator && $template) {
            $formGenerator = $this->mailGeneratorFactory->get($generator);
            $formGenerator->generateForm($form);
            $formGenerator->onSubmit($onSubmit);
        }

        try {
            $form->addSubmit('send')
                ->getControlPrototype()
                ->setName('button')
                ->setHtml('<i class="fa fa-cogs"></i> Generate');
        } catch (InvalidStateException $e) {
            // this is fine, submit was added by the generator
        }

        $form->setDefaults([
            'source_template_id' => $template ? $template->id : null,
        ]);
        return $form;
    }
}
