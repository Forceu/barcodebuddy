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
    protected $label = null;
    protected $value = null;
    protected $editorUi = null;
    protected $spaced = false;
    protected $scripts = array();

    function __construct(string $name, string $label, ?string $value, UiEditor $editorUi) {
        $this->name     = $name;
        $this->label    = $label;
        $this->value    = $value;
        $this->editorUi = $editorUi;
    }

    function addScript(?string $script): ElementBuilder {
        if (empty($script)) {
            return $this;
        }
        array_push($this->scripts, $script);
        return $this;
    }

    function addSpaces(): ElementBuilder {
        $this->spaced = true;
        return $this;
    }

    /**
     * @param bool $asHtml
     * @return string|UiEditor
     */
    function generate(bool $asHtml = false) {
        $result = $this->generateInternal() . $this->generateScript();
        if ($asHtml) {
            return $result;
        }

        $this->editorUi->addHtml($result);
        $this->spaced && $this->editorUi->addSpaces();
        return $this->editorUi;
    }

    private function generateScript(): string {
        $result = "";
        foreach ($this->scripts as $script) {
            $result = $result . "\n<script type='application/javascript'>" . $script . "</script>\n";
        }

        return $result;
    }

    protected function generateInternal(): string {
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
    private $id = null;

    /**
     * @return static
     */
    function setId(string $id): self {
        $this->id = $id;
        return $this;
    }

    /**
     * @return static
     */
    function setOnClick(string $onClick): self {
        $this->onClick = $onClick;
        return $this;
    }

    /**
     * @return static
     */
    function setRaised(bool $isRaised = true): self {
        $this->isRaised = $isRaised;
        return $this;
    }

    /**
     * @return static
     */
    function setHidden(bool $isHidden = true): self {
        $this->isHidden = $isHidden;
        return $this;
    }

    /**
     * @return static
     */
    function setIsColoured(bool $isColoured = true): self {
        $this->isColoured = $isColoured;
        return $this;
    }

    /**
     * @return static
     */
    function setIsAccent(bool $isAccent = true): self {
        $this->isColoured = true;
        $this->isAccent   = $isAccent;
        return $this;
    }

    /**
     * @param string|true $isDisabled
     * @return static
     *
     */
    function setDisabled(bool $isDisabled = true): self {
        $this->isDisabled = $isDisabled;
        return $this;
    }

    /**
     * @return static
     */
    function setAdditionalClasses(string $additionalClasses): self {
        $this->additionalClasses = $additionalClasses;
        return $this;
    }

    /**
     * @return static
     */
    function setSubmit(bool $isSubmit = true): self {
        $this->isSubmit = $isSubmit;
        return $this;
    }

    /**
     * @return static
     */
    function setValue(string $value): self {
        $this->value = $value;
        return $this;
    }

    protected function generateInternal(): string {
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

    /**
     * @return static
     */
    function disabled(bool $disabled): self {
        $this->isDisabled = $disabled;
        return $this;
    }

    /**
     * @return static
     */
    function useSpaces(bool $useSpaces): self {
        $this->useSpaces = $useSpaces;
        return $this;
    }

    /**
     * @param string $onCheckChanged
     * @param null|string $changeScript
     */
    function onCheckChanged(string $onCheckChanged, ?string $changeScript = null): ElementBuilder {
        $this->onChanged = $onCheckChanged;
        return $this->addScript($changeScript);
    }

    /**
     * @return string
     */
    protected function generateInternal(): string {
        return $this->editorUi->addCheckBoxInternal(
            $this->name,
            $this->label,
            ($this->value == 1), //Maybe change this to make it more readable / less prone to bugs
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

    /**
     * @return static
     */
    function pattern(string $pattern): self {
        $this->pattern = $pattern;
        return $this;
    }

    /**
     * @param string $type
     * @return static
     *
     */
    function type(string $type): self {
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

    /**
     * @return static
     */
    function disabled(bool $disabled = true): self {
        $this->disabled = $disabled;
        return $this;
    }

    /**
     * @return static
     */
    function autocompleteEntries($autocompleteEntries, $linked = null, $runAjax = false): self {
        $this->autocompleteEntries       = $autocompleteEntries;
        $this->autocompleteEntriesLinked = $linked;
        $this->autocompleteRunAjax       = $runAjax;
        return $this;
    }

    /**
     * @param bool $required
     * @return static
     *
     */
    function required(bool $required): self {
        $this->required = $required;
        return $this;
    }

    /**
     * @return static
     */
    function setDefault(array $array): self {
        if (isset($array[$this->name])) {
            $this->value = $array[$this->name];
        }
        return $this;
    }

    /**
     * @param array $minmax
     * @return static
     *
     */
    function minmax(array $minmax): self {
        $this->minmax = $minmax;
        return $this;
    }

    /**
     * @return static
     */
    function maxlength(int $maxlength): self {
        $this->maxlength = $maxlength;
        if ($this->type == "number") {
            // Max length does not work with "number"
            $this->type = "tel";
        }
        return $this;
    }

    /**
     * @param string $width
     * @return static
     *
     */
    function setWidth(string $width): self {
        $this->width = $width;
        return $this;
    }

    /**
     * @param int $minlength
     * @return static
     *
     */
    function minlength(int $minlength): self {
        $this->minlength = $minlength;
        if ($this->type == "number") {
            // Max length does not work with "number"
            $this->type = "tel";
        }
        return $this;
    }

    /**
     * @return static
     */
    function capitalize(bool $capitalize = true): self {
        $this->capitalize = $capitalize;
        return $this;
    }

    /**
     * @return static
     */
    function setPlaceholder(string $placeHolder): self {
        $this->placeHolder = $placeHolder;
        return $this;
    }

    /**
     * @param string $onfocusout
     * @return static
     *
     */
    function onfocusout(string $onfocusout): self {
        $this->onfocusout = $onfocusout;
        return $this;
    }

    /**
     * @return static
     */
    function onKeyUp(string $onkeyup): self {
        $this->onkeyup = $onkeyup;
        return $this;
    }

    /**
     * @param string $onKeyPress
     * @return static
     *
     */
    function onKeyPress(string $onKeyPress): self {
        $this->onKeyPress = $onKeyPress;
        return $this;
    }

    /**
     * @param false $floatingLabel
     * @return static
     *
     */
    function setFloatingLabel(bool $floatingLabel = false): self {
        $this->floatingLabel = $floatingLabel;
        return $this;
    }

    /**
     * @return string
     */
    protected function generateInternal(): string {
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


    function __construct(string $id, ?array $items, ?array $linkedItems = null, bool $runAjax = false) {
        $this->formId = $id;
        $this->items  = $items;
        if ($linkedItems != null) {
            $this->linkedItems  = $linkedItems[0];
            $this->linkedTarget = $linkedItems[1];
        }
        $this->runAjax = $runAjax;
    }

    function getHtml(): string {
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


    function __construct(bool $createForm = true, string $onSubmit = null, string $formname = null) {
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

    /**
     * @param null|string $value
     */
    function buildEditField(string $name, string $label, ?string $value = ""): EditFieldBuilder {
        $editor = new EditFieldBuilder($name, $label, $value, $this);
        return $editor;
    }

    function buildButton(string $name, string $label): ButtonBuilder {
        $editor = new ButtonBuilder($name, $label, null, $this);
        return $editor;
    }

    function addTableClass(TableGenerator $table): void {
        $this->addHtml($table->getHtml());
    }

    /**
     * @return static|string
     */
    function addCheckBoxInternal(string $name, string $label, bool $isChecked, bool $isDisabled = false, bool $useSpaces = true, string $onChanged = "", bool $asHtml = false) {
        $disabledHtml = "";
        $checkedHtml  = "";
        if ($isDisabled)
            $disabledHtml = "disabled";
        if ($isChecked)
            $checkedHtml = "checked";
        $html = '<label class="mdl-checkbox mdl-js-checkbox mdl-js-ripple-effect" for="' . $name . '">
                  <input type="checkbox" 
                         value="1" 
                         id="' . $name . '" 
                         name="' . $name . '" 
                         onchange="' . $onChanged . '"
                         class="mdl-checkbox__input" ' . $disabledHtml . ' ' . $checkedHtml . '>
                  <span class="mdl-checkbox__label">' . $label . '</span>
                </label><input type="hidden" value="0" name="' . $name . '_hidden"/>';

        if ($asHtml) {
            return $html;
        }

        $this->addHtml($html);
        $useSpaces && $this->addSpaces();

        return $this;
    }

    /**
     * @return static|string
     */
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
            return $this;
        }
    }

    /**
     * @param string $name
     * @param null|string $value
     * @return static|string
     *
     */
    function addHiddenField(string $name, ?string $value, $asHtml = false) {
        $html = '<input type="hidden" id="' . $name . '" value="' . $value . '" name="' . $name . '"/>';
        if ($asHtml) {
            return $html;
        }

        $this->addHtml($html);
        return $this;
    }

    /**
     * @param string $id
     * @param $asHtml
     * @return string|$this
     */
    function addLoadingSpinner(string $id, $asHtml = false): string|static {
        $html = '<div  id="' . $id . '" class="mdl-spinner mdl-js-spinner is-active"></div>';
        if ($asHtml) {
            return $html;
        }

        $this->addHtml($html);
        return $this;
    }

    function addSelectBox($name, $label, $valueLabels, $values = null, $preselected = null): void {
        if ($values == null) {
            $values = $valueLabels;
        }
        $this->addHtml('<div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label getmdl-select">
                    <input type="text" value="" class="mdl-textfield__input" id="' . $name . '" readonly>
                    <input id="' . $name . '_value" type="hidden" value="" name="' . $name . '">
                    <i class="mdl-icon-toggle__label material-icons">keyboard_arrow_down</i>
                    <label for="' . $name . '" class="mdl-textfield__label">' . $label . '</label>
                    <ul class="mdl-menu mdl-menu--bottom-left mdl-js-menu">');
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


    /**
     * @return static|string
     */
    function addUploadButton($name, $label, $asHtml = false) {
        $result = '<label class="fullWidth input-custom-file mdl-button mdl-js-button mdl-js-ripple-effect">
                      ' . $label . '
                      <input id="' . $name . '" name="' . $name . '" type="file">
                    </label>';
        if ($asHtml) {
            return $result;
        }
        $this->addHtml($result);
        $this->addSpaces();
        return $this;
    }

    /**
     * @param null|string $isChecked
     * @return static|string
     *
     */
    function addCheckbox(string $name, string $label, ?string $isChecked, bool $isDisabled = false, bool $useSpaces = true, bool $asHtml = false) {
        $checkedHtml = "";
        if ($isChecked) {
            $checkedHtml = "checked";
        }
        $disabledHtml = "";
        if ($isDisabled) {
            $disabledHtml = "disabled";
        }
        $result = '<label class="mdl-checkbox mdl-js-checkbox mdl-js-ripple-effect" for="' . $name . '">
                      <input type="checkbox" value="1" id="' . $name . '" name="' . $name . '" class="mdl-checkbox__input" ' . $disabledHtml . ' ' . $checkedHtml . '>
                      <span class="mdl-checkbox__label">' . $label . '</span>
                </label><input type="hidden" value="0" name="' . $name . '_hidden"/>';
        if ($asHtml) {
            if ($useSpaces)
                $result = $result . '&nbsp;&nbsp;';
            return $result;
        }
        if ($useSpaces)
            $this->addSpaces();

        $this->addHtml($result);
        return $this;
    }

    /**
     * @return static|string
     */
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
        }
        $this->addHtml($result);
        $this->addSpaces();
        return $this;
    }

    /**
     * @param string $htmlToDiv
     * @param null|string $class
     */
    function addDiv(string $htmlToDiv, $id = null, ?string $class = null): void {
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

    function addCollapsable($label, $content): void {
        $this->addButton("collapse", $label, null, false, false, false, false, false, "collapsible");
        $this->addHtml('<div class="content">');
        $this->addHtml($content);
        $this->addHtml('</div>');
        $this->addScript("addCollapsables()");
    }

    /**
     * @param string $name
     * @param string $label
     * @param null|string $onClick
     * @param bool $isRaised
     * @param bool $isHidden
     * @param bool $asHtml
     * @param bool $isColoured
     * @param bool $isDisabled
     * @param null|string $additionalClasses
     * @param null $id
     * @param bool $isSubmit
     * @param null $value
     * @param bool $isAccent
     * @return string
     */
    function addButton(string $name, string $label, ?string $onClick = null, bool $isRaised = false, bool $isHidden = false, bool $asHtml = false, bool $isColoured = false,
                       bool   $isDisabled = false, ?string $additionalClasses = null, $id = null, bool $isSubmit = false, $value = null, bool $isAccent = false): string {

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
        }
        $this->addHtml($result);
        $this->addSpaces();
        return $result;
    }

    function addErrorMessage($id, $label, $hint): void {
        $this->addHtml('<div id="' . $id . '" class="fl-hidden"><br><span style="color:red">' . $label . ' </span><i class="fas fa-question-circle"></i> <span class="tooltiptext">' . $hint . '</span>&nbsp;&nbsp;<br></div>');
    }

    /**
     * @return static|string
     */
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
        }
        $this->addHtml($html);
        $this->addSpaces();
        return $this;
    }

    function addLineBreak(int $count = 1): void {
        for ($i = 0; $i < $count; $i++) {
            $this->addHtml('<br>');
        }
    }

    /**
     * @param int $count
     */
    function addSpaces(int $count = 2): void {
        for ($i = 0; $i < $count; $i++) {
            $this->addHtml('&nbsp;');
        }
    }

    function addHtml(string $html): void {
        $this->htmlOutput = $this->htmlOutput . $html . "\n";
    }

    /**
     * @param string $htmlHeader
     * @param string $htmlBody
     * @param string $data
     * @param bool $asHtml
     * @return null|string
     */
    function addListItem(string $htmlHeader, string $htmlBody, string $data = "", bool $asHtml = false): ?string {
        //Add spaces if there is a checkbox
        if (strpos($htmlHeader, "mdl-checkbox") !== false) {
            $htmlBody = "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" . $htmlBody;
        }
        $result = "<li data-id=\"$data\" class=\"mdl-list__item mdl-list__item--two-line\" data-value=\"$data\"><span class=\"mdl-list__item-primary-content\">" . $htmlHeader . "
                                                    <span class=\"mdl-list__item-sub-title\">" . $htmlBody . "</span></span></li>\n";
        if ($asHtml)
            return $result;
        else
            $this->htmlOutput = $this->htmlOutput . $result;
    }

    function addScript(string $html): void {
        $this->htmlOutput = $this->htmlOutput . "\n" . "<script>" . $html . "</script>\n";
    }

    /**
     * @param string $file
     */
    function addScriptFile(string $file): void {
        $this->htmlOutput = $this->htmlOutput . "\n" . "<script src=\"$file\"></script>\n";
    }

    /**
     * @param int $maxSize
     */
    static function addTextWrap($text, int $maxSize = 12, $minSize = 10): string {
        return '<div style="max-width: ' . $maxSize . 'em; min-width: ' . $minSize . 'em; overflow-wrap: break-word; white-space: normal; overflow: auto;">' . $text . '</div>';
    }

    function getHtml(): string {
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

