<?php

/**
 * Barcode Buddy for Grocy
 *
 * PHP version 7
 *
 * LICENSE: This source file is subject to version 3.0 of the GNU General
 * Public License v3.0 that is attached to this project.
 *
 * @author     Marc Ole Bulling
 * @copyright  2020 Marc Ole Bulling
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU GPL v3.0
 * @since      File available since Release 1.4
 */


require_once __DIR__ . "/configProcessing.inc.php";

class ElementBuilder {
    protected $name = null;
    protected $id = null;
    protected $label = null;
    protected $value = null;
    protected $editorUi = null;
    protected $spaced = false;

    function __construct($name, $label, $value, $editorUi) {
        $this->name     = $name;
        $this->label    = $label;
        $this->value    = $value;
        $this->editorUi = $editorUi;
    }

    function setId($id) {
        $this->id = $id;
        return $this;
    }

    function addSpaces() {
        $this->spaced = true;
        return $this;
    }

    function generate($asHtml = false) {
        /** @noinspection PhpVoidFunctionResultUsedInspection */
        $result = $this->generateInternal();
        if ($asHtml) {
            return $result;
        }

        $this->editorUi->addHtml($result);
        $this->spaced && $this->editorUi->addSpaces();
        return $this->editorUi;
    }

    protected function generateInternal() {
        throw new Exception('generate needs to be overridden!');
    }
}

class ButtonBuilder extends ElementBuilder {
    private $onClick = null;
    private $isRaised = false;
    private $isHidden = false;
    private $isColoured = false;
    private $isDisabled = false;
    private $additionalClasses = null;
    private $isSubmit = false;
    private $isAccent = false;

    function setOnClick($onClick) {
        $this->onClick = $onClick;
        return $this;
    }

    function setRaised($isRaised = true) {
        $this->isRaised = $isRaised;
        return $this;
    }

    function setHidden($isHidden = true) {
        $this->isHidden = $isHidden;
        return $this;
    }

    function setIsColoured($isColoured = true) {
        $this->isColoured = $isColoured;
        return $this;
    }

    function setIsAccent($isAccent = true) {
        $this->isColoured = true;
        $this->isAccent   = $isAccent;
        return $this;
    }

    function setDisabled($isDisabled = true) {
        $this->isDisabled = $isDisabled;
        return $this;
    }

    function setAdditionalClasses($additionalClasses) {
        $this->additionalClasses = $additionalClasses;
        return $this;
    }

    function setSubmit($isSubmit = true) {
        $this->isSubmit = $isSubmit;
        return $this;
    }

    function setValue($value) {
        $this->value = $value;
        return $this;
    }

    protected function generateInternal() {
        return $this->editorUi->addButton($this->name,
            $this->label,
            $this->onClick,
            $this->isRaised,
            $this->isHidden,
            true,
            $this->isColoured,
            $this->isDisabled,
            $this->additionalClasses,
            $this->id,
            $this->isSubmit,
            $this->value,
            $this->isAccent);
    }
}

class CheckBoxBuilder extends ElementBuilder {
    private $isDisabled = false;
    private $useSpaces = true;
    private $onChanged = null;

    function disabled($disabled) {
        $this->isDisabled = $disabled;
        return $this;
    }

    function useSpaces($useSpaces) {
        $this->useSpaces = $useSpaces;
        return $this;
    }

    function onCheckChanged($onCheckChanged) {
        $this->onChanged = $onCheckChanged;
        return $this;
    }

    protected function generateInternal()
    {
        return $this->editorUi->addCheckBoxInternal(
            $this->name,
            $this->label,
            $this->value,
            $this->isDisabled,
            $this->useSpaces,
            $this->onChanged,
            true);
    }
}


class EditFieldBuilder extends ElementBuilder {
    private $pattern = null;
    private $type = "text";
    private $disabled = false;
    private $autocompleteEntries = null;
    private $autocompleteEntriesLinked = null;
    private $autocompleteRunAjax = false;
    private $required = true;
    private $minmax = null;
    private $maxlength = null;
    private $minlength = null;
    private $capitalize = false;
    private $onfocusout = null;
    private $isTimeInput = false;
    private $onkeyup = null;
    private $floatingLabel = true;
    private $placeHolder = null;
    private $onKeyPress = null;
    private $width = null;

    function pattern($pattern) {
        $this->pattern = $pattern;
        return $this;
    }

    function type($type) {
        // Max length does not work with "number"
        if ($this->maxlength != null && $type == "number") {
            $this->type = "tel";
        } else {
            if ($type == "time") {
                $this->isTimeInput = true;
                $this->type        = "text";
            } else {
                $this->type = $type;
            }
        }
        return $this;
    }

    function disabled($disabled = true) {
        $this->disabled = $disabled;
        return $this;
    }

    function autocompleteEntries($autocompleteEntries, $linked = null, $runAjax = false) {
        $this->autocompleteEntries       = $autocompleteEntries;
        $this->autocompleteEntriesLinked = $linked;
        $this->autocompleteRunAjax       = $runAjax;
        return $this;
    }

    function required($required) {
        $this->required = $required;
        return $this;
    }

    function setDefault($array) {
        if (isset($array[$this->name])) {
            $this->value = $array[$this->name];
        }
        return $this;
    }

    function minmax($minmax) {
        $this->minmax = $minmax;
        return $this;
    }

    function maxlength($maxlength) {
        $this->maxlength = $maxlength;
        if ($this->type == "number") {
            // Max length does not work with "number"
            $this->type = "tel";
        }
        return $this;
    }

    function setWidth($width) {
        $this->width = $width;
        return $this;
    }

    function minlength($minlength) {
        $this->minlength = $minlength;
        if ($this->type == "number") {
            // Max length does not work with "number"
            $this->type = "tel";
        }
        return $this;
    }

    function capitalize($capitalize = true) {
        $this->capitalize = $capitalize;
        return $this;
    }

    function setPlaceholder($placeHolder) {
        $this->placeHolder = $placeHolder;
        return $this;
    }

    function onfocusout($onfocusout) {
        $this->onfocusout = $onfocusout;
        return $this;
    }

    function onKeyUp($onkeyup) {
        $this->onkeyup = $onkeyup;
        return $this;
    }

    function onKeyPress($onKeyPress) {
        $this->onKeyPress = $onKeyPress;
        return $this;
    }

    function setFloatingLabel($floatingLabel = false) {
        $this->floatingLabel = $floatingLabel;
        return $this;
    }

    protected function generateInternal()
    {
        return $this->editorUi->addEditFieldInternal(
            $this->name, $this->label, $this->value, $this->pattern,
            $this->type, $this->disabled, $this->autocompleteEntries,
            $this->autocompleteEntriesLinked, $this->autocompleteRunAjax,
            $this->required, $this->minmax, $this->maxlength, $this->minlength,
            $this->capitalize, $this->onfocusout, $this->isTimeInput,
            $this->onkeyup, $this->floatingLabel, $this->placeHolder, $this->onKeyPress,
            $this->width, true);
    }

}


class AutoComplete {
    private $formId = null;
    private $items = null;
    private $linkedItems = null;
    private $linkedTarget = null;
    private $runAjax = false;


    function __construct($id, $items, $linkedItems = null, $runAjax = false) {
        $this->formId = $id;
        $this->items  = $items;
        if ($linkedItems != null) {
            $this->linkedItems  = $linkedItems[0];
            $this->linkedTarget = $linkedItems[1];
        }
        $this->runAjax = $runAjax;
    }

    function getHtml() {
        $ajaxAsString = var_export($this->runAjax, true);
        $html         = 'var autocompl_' . $this->formId . ' = ' . json_encode($this->items) . ";\n";
        if ($this->linkedItems != null) {
            $html = $html . 'var autocompl_' . $this->formId . '_link = ' . json_encode($this->linkedItems) . ";\n";
            $html = $html . 'autocomplete(document.getElementById("' . $this->formId . '"), autocompl_' . $this->formId . ',autocompl_' . $this->formId . '_link, document.getElementById("' . $this->linkedTarget . "\"), $ajaxAsString);\n";
        } else {
            $html = $html . 'var autocompl_' . $this->formId . "_link = [];\n"; //In order to dynamically populate items, an empty array has to be added to the JS code
            $html = $html . 'autocomplete(document.getElementById("' . $this->formId . '"), autocompl_' . $this->formId . ",null,null,$ajaxAsString);\n";
        }
        return $html;
    }
}


class UiEditor {

    private $htmlOutput = "";
    private $autoComplete = null;
    private $checkBoxes = null;
    private $createForm = null;
    private $hasTimeInput = false;


    function __construct($createForm = true, $onSubmit = null, $formname = null) {
        global $CONFIG;
        $onSubmitHtml = "";
        if ($onSubmit != null) {
            $onSubmitHtml = "onsubmit=\"$onSubmit\"";
        }
        if ($createForm) {
            if ($formname == null)
                $name = 'editform' . rand();
            else
                $name = $formname;
             $this->htmlOutput = '<div id="' . $name . '"> <form enctype="multipart/form-data" name="' . $name . '" ' . $onSubmitHtml . ' id="' . $name . '_form" method="post" action="' . $CONFIG->getPhpSelfWithBaseUrl() . '" >';
        }
        $this->autoComplete = array();
        $this->checkBoxes   = array();
        $this->createForm   = $createForm;
    }

    function buildEditField($name, $label, $value = "") {
        $editor = new EditFieldBuilder($name, $label, $value, $this);
        return $editor;
    }

    function buildButton($name, $label) {
        $editor = new ButtonBuilder($name, $label, $this);
        return $editor;
    }

    function addTableClass($table) {
        $this->addHtml($table->getHtml());
    }

    function addCheckBoxInternal(
            $name,
            $label,
            $value,
            $isDisabled = false,
            $useSpaces = true,
            $onChanged = "",
            $asHtml = false) {
        $html = '<label class="mdl-checkbox mdl-js-checkbox mdl-js-ripple-effect" for="' . $name . '">
                  <input type="checkbox" 
                         value="1" 
                         id="' . $name . '" 
                         name="' . $name . '" 
                         onchange="' . $onChanged . '"
                         class="mdl-checkbox__input" ' . ($isDisabled && "disabled") . ' ' . ($value && "checked") . '>
                  <span class="mdl-checkbox__label">' . $label . '</span>
                </label><input type="hidden" value="0" name="' . $name . '_hidden"/>';

        if ($asHtml) {
            return $html;
        }

        $this->addHtml($html);
        $useSpaces && $this->addSpaces();

        return $this;
    }

    function addEditFieldInternal($name, $label, $value, $pattern = null, $type = "text", $disabled = false, $autocompleteEntries = null, $autocompleteEntriesLinked = null,
                                  $autocompleteRunAjax = false, $required = true, $minmax = null, $maxlength = null, $minlength = null, $capitalize = false, $onfocusout = null, $isTimeInput = false,
                                  $onKeyUp = null, $floatingLabel = true, $placeHolder = null, $onKeyPress = null, $width = null, $asHtml = false) {

        $minmaxHtml = "";
        if ($minmax != null) {
            if ($minmax[0] != null) {
                $minmaxHtml = 'min="' . $minmax[0] . '"';
            }
            if ($minmax[1] != null) {
                $minmaxHtml = $minmaxHtml . ' max="' . $minmax[1] . '"';
            }
        }
        $timeInputHtml = '';
        if ($isTimeInput) {
            $this->hasTimeInput = true;
            $timeInputHtml      = ' time-input';
        }
        $requiredHtml = "";
        if ($required) {
            $requiredHtml = "required";
        }
        $widthHtml = "";
        if ($width != null) {
            $widthHtml = "style='width:$width'";
        }
        $maxLenghtHtml = "";
        if ($maxlength != null) {
            $maxLenghtHtml = 'maxlength="' . $maxlength . '"';
        }
        $minLenghtHtml = "";
        if ($minlength != null) {
            $minLenghtHtml = 'minlength="' . $minlength . '"';
        }
        $capitalizeHtml = "";
        if ($capitalize) {
            $capitalizeHtml = 'style="text-transform: uppercase;"';
        }
        if ($autocompleteEntries !== null) {
            array_push($this->autoComplete, new AutoComplete($name, $autocompleteEntries, $autocompleteEntriesLinked, $autocompleteRunAjax));
        }
        $disabledHtml = "";
        if ($disabled) {
            $disabledHtml = "disabled";
            $requiredHtml = "";
            $minimumHtml  = "";
        }
        $onkeyupHtml = "";
        if ($onKeyUp != null) {
            $onkeyupHtml = 'onKeyUp="' . $onKeyUp . '"';
        }
        $onKeyPressHtml = "";
        if ($onKeyPress != null) {
            $onKeyPressHtml = 'onkeypress="' . $onKeyPress . '"';
        }
        $onfocusoutHtml = "";
        if ($onfocusout != null) {
            $onfocusoutHtml = 'onfocusout="' . $onfocusout . '"';
        }
        $patternHtml = "";
        if ($pattern != null) {
            $patternHtml = "pattern=\"$pattern\"";
        }
        $placeholderHtml = "";
        if ($placeHolder != null) {
            $placeholderHtml = "placeholder=\"$placeHolder\"";
        }
        $floatingLabelHtml = "";
        if ($floatingLabel) {
            $floatingLabelHtml = "mdl-textfield--floating-label";
        }
        $result = '<div ' . $widthHtml . ' class="mdl-textfield mdl-js-textfield ' . $floatingLabelHtml . '">
                <input ' . $onkeyupHtml . ' ' . $onKeyPressHtml . ' ' . $placeholderHtml . ' ' . $capitalizeHtml . ' ' . $onfocusoutHtml . ' autocomplete="off" class="mdl-textfield__input' . $timeInputHtml . '"  ' . $maxLenghtHtml . ' ' . $minmaxHtml . ' ' . $minLenghtHtml . ' ' . $requiredHtml . ' ' . $disabledHtml . ' ' . $patternHtml . ' value="' . $value . '" type="' . $type . '" name="' . $name . '" id="' . $name . '">
                <label id="' . $name . '_label" class="mdl-textfield__label" for="' . $name . '">' . $label . '</label>
              </div>';
        if ($asHtml) {
            return $result;
        } else {
            $this->addHtml($result);
            $this->addSpaces();
        }
    }

    function addHiddenField($name, $value, $asHtml = false) {
        $html = '<input type="hidden" id="' . $name . '" value="' . $value . '" name="' . $name . '"/>';
        if ($asHtml) {
            return $html;
        } else {
            $this->addHtml($html);
        }
    }

    function addSelectBox($name, $label, $valueLabels, $values = null, $preselected = null) { //TODO disabled
        if ($values == null) {
            $values = $valueLabels;
        }
        $this->addHtml('<div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label getmdl-select">
                    <input type="text" value="" class="mdl-textfield__input" id="' . $name . '" readonly>
                    <input id="' . $name . '_value" type="hidden" value="" name="' . $name . '">
                    <i class="mdl-icon-toggle__label material-icons">keyboard_arrow_down</i>
                    <label for="' . $name . '" class="mdl-textfield__label">' . $label . '</label>
                    <ul for="' . $name . '" class="mdl-menu mdl-menu--bottom-left mdl-js-menu">');
        for ($i = 0; $i < count($values); $i++) {
            $preHtml = "";
            if ($preselected !== null && $i == $preselected) {
                $preHtml = 'data-selected="true"';
            }
            $this->addHtml('<li class="mdl-menu__item" data-val="' . $values[$i] . '" ' . $preHtml . '>' . $valueLabels[$i] . '</li>');
        }
        $this->addHtml('</ul></div>');
        $this->addSpaces();
    }


    function addUploadButton($name, $label, $asHtml = false) {
        $result = '<label class="fullWidth input-custom-file mdl-button mdl-js-button mdl-js-ripple-effect">
                      ' . $label . '
                      <input id="' . $name . '" name="' . $name . '" type="file">
                    </label>';
        if ($asHtml) {
            return $result;
        } else {
            $this->addHtml($result);
            $this->addSpaces();
        }
    }

    function addCheckbox($name, $label, $isChecked, $isDisabled = false, $useSpaces = true) {
        $checkedHtml = "";
        if ($isChecked) {
            $checkedHtml = "checked";
        }
        $disabledHtml = "";
        if ($isDisabled) {
            $disabledHtml = "disabled";
        }
        $this->addHtml('<label class="mdl-checkbox mdl-js-checkbox mdl-js-ripple-effect" for="' . $name . '">
                  <input type="checkbox" value="1" id="' . $name . '" name="' . $name . '" class="mdl-checkbox__input" ' . $disabledHtml . ' ' . $checkedHtml . '>
                  <span class="mdl-checkbox__label">' . $label . '</span>
                </label><input type="hidden" value="0" name="' . $name . '_hidden"/>');
        if ($useSpaces)
            $this->addSpaces();
    }

    function addSubmitButton($name, $label, $isRaised = true, $isDisabled = false, $asHtml = false, $isColoured = true, $onClick = null, $value = null) {
        $isColouredHtml = "";
        if ($isColoured) {
            $isColouredHtml = "mdl-button--colored";
        }
        $onclickHtml = "";
        if ($onClick != null) {
            $onclickHtml = 'onclick="' . $onClick . '"';
        }
        $valueHtml = "";
        if ($value != null) {
            $valueHtml = 'value="' . $value . '"';
        }
        $raisedHtml = "";
        if ($isRaised) {
            $raisedHtml = "mdl-button--raised";
        }

        $result = '<button class="fullWidth mdl-button ' . $isColouredHtml . ' ' . $raisedHtml . ' mdl-js-button mdl-js-ripple-effect" ' . $onclickHtml . ' type="submit" name="' . $name . '" id="' . $name . '" ' . $valueHtml . '>' . $label . '</button>';
        if ($asHtml) {
            return $result;
        } else {
            $this->addHtml($result);
            $this->addSpaces();
        }
    }

    function addDiv($htmlToDiv, $id = null, $class = null) {
        $idHtml = "";
        if ($id != null) {
            $idHtml = 'id="' . $id . '"';
        }
        $classHtml = "";
        if ($class != null) {
            $classHtml = 'class="' . $class . '"';
        }

        $this->addHtml("<div $idHtml $classHtml >$htmlToDiv</div>");
    }

    function addCollapsable($label, $content) {
        $this->addButton("collapse", $label, null, false, false, false, false, false, "collapsible");
        $this->addHtml('<div class="content">');
        $this->addHtml($content);
        $this->addHtml('</div>');
        $this->addScript("addCollapsables()");
    }

    function addButton($name, $label, $onClick = null, $isRaised = false, $isHidden = false, $asHtml = false, $isColoured = false,
                       $isDisabled = false, $additionalClasses = null, $id = null, $isSubmit = false, $value = null, $isAccent = false) {

        if ($id == null)
            $id = $name;
        if ($value == null)
            $value = $label;
        $raisedHtml = "";
        if ($isRaised) {
            $raisedHtml = "mdl-button--raised";
        }
        $additionalHtml = "";
        if ($additionalClasses != null) {
            $additionalHtml = $additionalClasses;
        }
        $disabledHtml = "";
        if ($isDisabled) {
            $disabledHtml = "disabled";
        }
        $isColouredHtml = "";
        if ($isColoured) {
            $isColouredHtml = "mdl-button--colored";
        }
        $isAccentHtml = "";
        if ($isAccent) {
            $isAccentHtml = "mdl-button--accent";
        }
        $hiddenHtml = "";
        if ($isHidden) {
            $hiddenHtml = 'style="visibility:hidden"';
        }
        $onclickHtml = "";
        if ($onClick != null) {
            $onclickHtml = 'onclick="' . $onClick . '"';
        }
        $type = "button";
        if ($isSubmit) {
            $type = "submit";
        }

        $result = '<button ' . $hiddenHtml . ' ' . $disabledHtml . ' class="mdl-button mdl-js-button ' . $additionalHtml . ' ' . $isColouredHtml . ' ' . $isAccentHtml . ' ' . $raisedHtml . '" id="' . $id . '" name="' . $name . '" ' . $onclickHtml . ' type="' . $type . '"  value="' . $value . '">' . $label . '</button>';

        if ($asHtml) {
            return $result;
        } else {
            $this->addHtml($result);
            $this->addSpaces();
        }
    }

    function addErrorMessage($id, $label, $hint) {
        $this->addHtml('<div id="' . $id . '" class="fl-hidden"><br><span style="color:red">' . $label . ' </span><i class="fas fa-question-circle"></i> <span class="tooltiptext">' . $hint . '</span>&nbsp;&nbsp;<br></div>');
    }

    function addRadioButton($name, $label, $value, $isChecked = false, $returnAsHtml = false, $isDisabled = false) {

        $disabledHtml = "";
        if ($isDisabled) {
            $disabledHtml = "disabled";
        }

        if (!isset($this->checkBoxes[$name])) {
            $this->checkBoxes[$name] = 0;
            $number                  = 0;
        } else {
            $this->checkBoxes[$name]++;
            $number = $this->checkBoxes[$name];
        }
        $checked = "";
        if ($isChecked) {
            $checked = 'checked';
        }
        $html = '<label class="mdl-radio mdl-js-radio mdl-js-ripple-effect" for="' . $name . '_' . $number . '">
                  <input type="radio" ' . $disabledHtml . ' id="' . $name . '_' . $number . '" class="mdl-radio__button" name="' . $name . '" value="' . $value . '" ' . $checked . '>
                  <span class="mdl-radio__label">' . $label . '</span>
                </label>';
        if ($returnAsHtml) {
            return $html;
        } else {
            $this->addHtml($html);
            $this->addSpaces();
        }
    }

    function addLineBreak($count = 1) {
        for ($i = 0; $i < $count; $i++) {
            $this->addHtml('<br>');
        }
    }

    function addSpaces($count = 2) {
        for ($i = 0; $i < $count; $i++) {
            $this->addHtml('&nbsp;');
        }
    }

    function addHtml($html) {
        $this->htmlOutput = $this->htmlOutput . $html . "\n";
    }

    function addScript($html) {
        $this->htmlOutput = $this->htmlOutput . "\n" . "<script>" . $html . "</script>\n";
    }

    static function addTextWrap($text, $maxSize = 12, $minSize = 10) {
        return '<div style="max-width: ' . $maxSize . 'em; min-width: ' . $minSize . 'em; overflow-wrap: break-word; white-space: normal; overflow: auto;">' . $text . '</div>';
    }

    function getHtml() {
        if ($this->createForm) {
            $result = $this->htmlOutput . '</form></div>';
        } else {
            $result = $this->htmlOutput;
        }
        if (count($this->autoComplete) > 0) {
            $result = $result . "<script>\n";
            foreach ($this->autoComplete as $auto) {
                $result = $result . $auto->getHtml();
            }
            $result = $result . "</script>\n";
        }
        return $result;
    }
}

