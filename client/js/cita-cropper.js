(function($) {
  $.entwine('ss', function($) {
    $('.cita-cropper').entwine({
      onmatch: function(e) {
        if ($(this).find('img').length) {
          var fieldHolder = $(this).parents('.cita-cropper-field:eq(0)'),
              me          = $(this),
              cropperInstance = null,
              doInit      = function(me) {
                              if (cropperInstance) {
                                return
                              }

                              const containerWidth = fieldHolder.find(`input[name="CropperField_${me.attr('data-name')}_ContainerWidth"]`).val()
                              const containerHeight = fieldHolder.find(`input[name="CropperField_${me.attr('data-name')}_ContainerHeight"]`).val()
                              const actualContainerWidth = parseInt(me.width())
                              const actualContainerHeight = parseInt(me.height())
                              const widthRatio = actualContainerWidth / containerWidth
                              const heightRatio = actualContainerHeight / containerHeight

                              var image           =   me.find('img')[0],
                                  name            =   me.attr('data-name'),
                                  cords           =   {
                                                          left    :   parseInt(fieldHolder.find(`input[name="CropperField_${name}_CropperX"]`).val() * widthRatio),
                                                          top     :   parseInt(fieldHolder.find(`input[name="CropperField_${name}_CropperY"]`).val() * heightRatio),
                                                          width   :   parseInt(fieldHolder.find(`input[name="CropperField_${name}_CropperWidth"]`).val() * widthRatio),
                                                          height  :   parseInt(fieldHolder.find(`input[name="CropperField_${name}_CropperHeight"]`).val() * heightRatio),
                                                      },
                                  ratio           =   fieldHolder.find(`input[name="CropperField_${name}_CropperRatio"]`).val(),
                                  cropper         =   new Cropper(image, {
                                                        viewMode: 3,
                                                        aspectRatio: ratio ? ratio : NaN,
                                                        zoomable: false,
                                                        crop: function(e) {
                                                            var w = Math.round(cropper.getCanvasData().width),
                                                                h = Math.round(cropper.getCanvasData().height),
                                                                cx = Math.round(cropper.getCropBoxData().left),
                                                                cy = Math.round(cropper.getCropBoxData().top),
                                                                cw = Math.round(cropper.getCropBoxData().width),
                                                                ch = Math.round(cropper.getCropBoxData().height);

                                                            fieldHolder.find(`input[name="CropperField_${name}_ContainerWidth"]`).val(w);
                                                            fieldHolder.find(`input[name="CropperField_${name}_ContainerHeight"]`).val(h);
                                                            fieldHolder.find(`input[name="CropperField_${name}_CropperX"]`).val(cx);
                                                            fieldHolder.find(`input[name="CropperField_${name}_CropperY"]`).val(cy);
                                                            fieldHolder.find(`input[name="CropperField_${name}_CropperWidth"]`).val(cw);
                                                            fieldHolder.find(`input[name="CropperField_${name}_CropperHeight"]`).val(ch);
                                                        },
                                                        ready: function() {
                                                            cropper.setCropBoxData(cords);
                                                        }
                                                      });

                              cropperInstance = cropper;
                            };

          onElementShow($(this)[0], visible => {
            if (visible) {
              window.dispatchEvent(new Event('resize'))
              doInit(me)
            }
          })

          domWatcher($(this).parents('.cita-cropper-field').find('.uploadfield.field')[0], mutated =>{
            const list = mutated.filter(o => {
              return o.type == 'childList'
            })

            const uploadedFileRemoved = list.find(o => o.removedNodes.length && o.removedNodes[0].classList && o.removedNodes[0].classList.contains('uploadfield-item'))
            if (uploadedFileRemoved) {
              cropperInstance.destroy()
              me.find('img').remove()
            }
          })
        }
      }
    });
  });

  function onElementShow(element, callback) {
    var options = {
      root: document.documentElement,
    };

    var observer = new IntersectionObserver((entries, observer) => {
      entries.forEach(entry => {
        callback(entry.intersectionRatio > 0);
      });
    }, options);

    observer.observe(element);
  }

  function domWatcher(selector, callback) {
    const targetNode = (typeof selector == 'object') ? selector : document.querySelector(selector);
    const observerOptions = {
      childList: true,
      attributes: true,
      // Omit (or set to false) to observe only changes to the parent node
      subtree: true
    }

    const observer = new MutationObserver(callback);
    observer.observe(targetNode, observerOptions);
  }
}(jQuery));
