<?php

namespace Cita\ImageCropper\Fields;

use Cita\ImageCropper\Model\CitaCroppableImage as Picture;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_ActionMenu;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\Forms\GridField\GridFieldSortableHeader;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\HasManyList;
use SilverStripe\ORM\ManyManyList;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;

class CroppableImageField extends CompositeField
{
    protected $fields = [];
    protected $picture;
    protected $pictureTitle;
    protected $desktopImage;
    protected $tabletImage;
    protected $phoneImage;
    protected $picDesktopWidth;
    protected $picDesktopHeight;
    protected $picTabletWidth;
    protected $picTabletHeight;
    protected $picPhoneWidth;
    protected $picPhoneHeight;
    protected $manyMode = false;
    protected $performDelete = false;
    protected $sortField;
    protected $dbFields = [];
    protected $dataToSave = [];
    protected $ratio;

    public function __construct($name, $title = null, $owner = null)
    {
        $this->picture = $owner->{$name}();
        $this->dbFields = array_keys(Picture::singleton()->config()->db);

        $this->manyMode = $this->picture instanceof ManyManyList || $this->picture instanceof HasManyList;

        if ($this->manyMode) {
            $this->initManyMode($name);
        } else {
            $this->initSingleMode($name, $this->picture);
        }

        parent::__construct($this->fields);

        $this->setName($name);
        $this->setTitle($title ?? self::name_to_label($name));

        $this->addExtraClass('cita-cropper-field');

        if ($this->manyMode) {
            $this->addExtraClass('multi-mode');
        }
    }

    public function setAdditionalDBFields($fields)
    {
        if (!empty($fields)) {
            $this->dbFields = array_merge($this->dbFields, $fields);
        }

        return $this;
    }

    public function setDimensions($dimensions)
    {
        foreach ($dimensions as $device => $dimension) {
            $dimension = (object) $dimension;
            $deviceFieldWidth = "pic{$device}Width";
            $deviceFieldHeight = "pic{$device}Height";

            $this->{$deviceFieldWidth} = $dimension->Width;
            $this->{$deviceFieldHeight} = $dimension->Height;

            if (!empty($this->fields[$device])) {
                $this->fields[$device]->setDescription("Width: {$dimension->Width}px, Height: {$dimension->Height}px");
            }
        }

        return $this;
    }

    public function hasData()
    {
        return true;
    }

    public function setSubmittedValue($value, $data = null)
    {
        $name = $this->name;

        if ($data && !empty($data["CropperField_{$name}"]["Files"])) {
            foreach ($this->dbFields as $fieldName) {
                $localisedFieldName = "CropperField_{$name}_{$fieldName}";
                if (isset($data[$localisedFieldName])) {
                    $this->dataToSave[$fieldName] = $data[$localisedFieldName];
                }
            }
        } else {
            $this->performDelete = true;
        }

        return $this;
    }

    public function saveInto($data)
    {
        if ($this->name) {
            if ($this->manyMode) {
                $this->saveMany($data);
            } else {
                $this->saveSingle($data);
            }
        }
    }

    public function setSortField($fieldName)
    {
        if ($this->sortField) {
            $this->sortField->setSortField($fieldName);
        }

        return $this;
    }

    public function setCropperRatio($ratio)
    {
        $this->Ratio = $ratio;

        if (isset($this->fields['CropperRatio'])) {
            $this->fields['CropperRatio']->setValue($ratio);
        }

        return $this;
    }

    public function setFolderName($folderName)
    {
        $this->fields['Uploader']->setFolderName($folderName);

        return $this;
    }

    private function initManyMode($name, $title = null)
    {
        $this->initSingleMode($name);

        $this->fields['GridField'] = GridField::create(
            "CropperField_{$name}",
            'Uploaded pictures',
            $this->picture
        )->setConfig($this->makeConfig())
            ->addExtraClass('picture-field-gridfield')
        ;
    }

    private function makeConfig()
    {
        $config = GridFieldConfig::create();

        $config->addComponent($sort = new GridFieldSortableHeader());
        $config->addComponent($columns = new GridFieldDataColumns());
        $config->addComponent(new GridFieldEditButton());
        $config->addComponent(new GridFieldDeleteAction());
        $config->addComponent(new GridField_ActionMenu());
        $config->addComponent($pagination = new GridFieldPaginator(null));
        $config->addComponent(new GridFieldDetailForm());
        $config->addComponent($this->sortField = GridFieldOrderableRows::create('Sort'));

        $columns->setDisplayFields([
            'Desktop.CMSThumbnail' => 'Desktop',
            'Tablet.CMSThumbnail' => 'Tablet',
            'Phone.CMSThumbnail' => 'Mobile',
            'Text' => [
                'title' => 'Title & caption',
                'callback' => function ($pic) {
                    return '<dl>
                        <dt>Title</dt>
                        <dd>' . ($pic->Title ?? '<em>not set</em>') . '</dd>
                        <dt>Caption</dt>
                        <dd>' . ($pic->Caption ?? '<em>not set</em>') . '</dd>
                    </dl>';
                },
            ],
        ])->setFieldCasting([
            'Text' => 'HTMLFragment->RAW',
        ]);

        $sort->setThrowExceptionOnBadDataType(false);
        $pagination->setThrowExceptionOnBadDataType(false);

        return $config;
    }

    private function initSingleMode($name, $picture = null)
    {
        $this->fields['Uploader'] = UploadField::create(
            "CropperField_{$name}",
            'Image'
        )
            ->setAllowedMaxFileNumber(1)
            ->setAllowedExtensions(['png', 'gif', 'jpeg', 'jpg'])
        ;

        $this->fields['CropperRatio'] = HiddenField::create("CropperField_{$name}_CropperRatio")->setValue($this->Ratio);
        $this->fields['ContainerWidth'] = HiddenField::create("CropperField_{$name}_ContainerWidth")->setValue($picture->ContainerWidth);
        $this->fields['ContainerHeight'] = HiddenField::create("CropperField_{$name}_ContainerHeight")->setValue($picture->ContainerHeight);
        $this->fields['CropperX'] = HiddenField::create("CropperField_{$name}_CropperX")->setValue($picture->CropperX);
        $this->fields['CropperY'] = HiddenField::create("CropperField_{$name}_CropperY")->setValue($picture->CropperY);
        $this->fields['CropperWidth'] = HiddenField::create("CropperField_{$name}_CropperWidth")->setValue($picture->CropperWidth);
        $this->fields['CropperHeight'] = HiddenField::create("CropperField_{$name}_CropperHeight")->setValue($picture->CropperHeight);

        $hasImage = $picture && $picture->exists() && $picture->Original()->exists();

        $this->fields['Canvas'] = LiteralField::create(
            "CropperField_{$name}_Canvas",
            '<div class="cita-cropper-holder">
                <div class="cita-cropper" data-name="' . $name . '">
                    ' . ($hasImage ? ('<img src="' . $picture->Original()->ScaleWidth(768)->URL . '?timestamp=' . time() . '" />') : '') . '
                </div>
            </div>'
        );

        if ($hasImage) {
            $this->fields['Uploader']
                ->setValue($picture->Original())
                ->addExtraClass('is-collapsed')
            ;
        }
    }

    private function saveMany(&$data)
    {
        if ($picID = $this->saveSingle($data, true)) {
            $this->picture->add($picID);
        }
    }

    private function saveSingle(&$data, $return = false)
    {
        $name = $this->name;

        if ($this->performDelete && $data->{$name}()->exists()) {
            $data->{$name}()->delete();

            return;
        }

        $pic = $return ? Picture::create() : ($data->{$name}()->exists() ? $data->{$name}() : Picture::create());

        $image = $this->fields['Uploader']->value();

        if (empty($image)) {
            return;
        }

        $image = !empty($image) && !empty($image['Files']) ? $image['Files'][0] : null;
        $pic = $pic->update(array_merge(
            $this->dataToSave,
            [
                'OriginalID' => $image,
            ]
        ));

        if ($return) {
            return $pic->write();
        }

        $this->setValue($pic->write());
        $data = $data->setCastedField($name, $this->dataValue());
    }
}
