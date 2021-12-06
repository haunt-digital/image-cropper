# CitaNZ's Image Cropper
It's a continious development of Salted Herring's Cropper Field for SilverStripe - 4 (https://github.com/salted-herring/salted-cropper)

### Usage
1. Install
  ```
  composer require cita/image-cropper
  ```

2. /dev/build?flush=all

3. Sample code:

    ```php
    ...
    use Cita\ImageCropper\Model\CitaCroppableImage;
    use Cita\ImageCropper\Fields\CroppableImageField;
    ...
    private static $has_one = array(
        'Photo'     =>  CitaCroppableImage::class
    );


    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        ...
        // adding a free cropper
        $fields->addFieldToTab(TAB_NAME, CroppableImageField::create('Photo', A_TITLE_TO_THE_FILED, $this));

        // adding cropper with ratio
        $fields->addFieldToTab(TAB_NAME, CroppableImageField::create('Photo', A_TITLE_TO_THE_FILED, $this)->setCropperRatio(16/9));
        ...
        return $fields;        
    }

    ```

4. Add image > upload/select > save > edit > do your cropping > save

5. Output
    ```html
    $Photo
    $Photo.Cropped
    $Photo.Cropped.SetWidth(100)
    ```

### AWS support
If you are using SS's S3 assets module to store your images, you may also want to add this into your site's config yaml
```
SilverStripe\Admin\LeftAndMain:
  extra_requirements_javascript:
    - 'resources/vendor/cita/image-cropper/client/js/s3-workaround.js'
```
