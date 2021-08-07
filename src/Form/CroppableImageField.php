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
    protected $performDelete = false;
    protected $dbFields = [];
    protected $dataToSave = [];
    protected $ratio;

    public function __construct($name, $title = null, $owner = null)
    {
        $this->picture = $owner->{$name}();
        if ($this->picture instanceof ManyManyList || $this->picture instanceof HasManyList) {
            throw new \Exception("{$name}() is not a HasOne relation!", 1);
            die;
        }

        $this->dbFields = array_keys(Picture::singleton()->config()->db);

        $this->initSingleMode($name, $this->picture);

        parent::__construct($this->fields);

        $this->setName($name);
        $this->setTitle($title ?? self::name_to_label($name));

        $this->addExtraClass('cita-cropper-field');
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
            $name = $this->name;

            if ($this->performDelete && $data->{$name}()->exists()) {
                $data->{$name}()->delete();

                return;
            }

            $pic = $data->{$name}()->exists() ? $data->{$name}() : Picture::create();

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

            $this->setValue($pic->write());
            $data = $data->setCastedField($name, $this->dataValue());
        }
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
}
