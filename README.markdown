# What is it?
A PHP class to help generate a web form, based on an input of text rules separated with pipes.
Based on GUMP (https://github.com/Wixel/GUMP), by Sean Nieuwoudt (http://twitter.com/SeanNieuwoudt)

Can be used to pull an entity directly from the database, and present table columns as inputs.
You can then validate those inputs, and pass back into the database.

Script was built to handle 30-150 form elements that needed to have various rules.
When the form is submitted, rules are then passed into GUMP for input value validation.

# Getting Started


# How to Use (extended):
`````
// build array
$input_rules = array(
    'id'          => 'nodisplay'
    ,'title'      => 'select|options,array-titleOptions'
    ,'firstName'  => 'text|max_len,4'
    ,'lastName'   => 'text|after,(Family name)'
    ,'dob'	      => 'datetime|id,dob_datePicker'
    );

$arrayOptions  = array(
    'titleOptions' => array(
        'Mr'=>'Mr'
        ,'Mrs'=>'Mrs'
        ,'Ms'=>'Ms'
        ,'Miss'=>'Miss'
        )
    );

$input_javascript = array(
    //http://trentrichardson.com/examples/timepicker/
    "$('#dob_datePicker').datetimepicker({
                    dateFormat: 'yy-mm-dd'
                    ,timeFormat: 'HH:mm z'
                    ,minuteGrid: 15
                    ,numberOfMonths: 1
                    ,minDate: -90
                    ,maxDate: 1
                    ,addSliderAccess: true
                    ,sliderAccessArgs: { touchonly: false }
                }); \n" 
    );

$inputColumns = array('id', 'title', 'firstName', 'lastName', 'dob', 'phone' );
$formBuilder = new \GumpFormBuilder\GumpFormBuilder(); // rules controller
	
if( isset($request->get->view) ) {
    $formBuilder->form_viewOnly( array('firstName', 'lastName') ); // Allows you to rearrange your view.
}


$formBuilder->form_rules($input_rules);
$formBuilder->form_rules_options($arrayOptions);

$inputArray = $formBuilder->run($inputColumns); // runs through all the fields and creates the HTML
$otherInputArray = $formBuilder->getOtherInputs(); 			// places non-prioritised inputs here
        
$html .= <<<HEREDOC
        <form name="registerForm" id="registerForm" action="register.php" method="post" enctype="application/x-www-form-urlencoded">
            <fieldset>
                {$inputArray}
                <button type='submit' name='submit' value="Register">Register</button>
            </fieldset>
            
            <fieldset>
                {$otherInputArray} 
            </fieldset>
        </form>
HEREDOC;
            
$html .= $formBuilder->outputJavaScript($input_javascript);
echo $html;
`````



# Available Elements

Input form options available:
* text `(default if none is entered)`
* select dropdown
 * options `array of key-value pairs`
* checkbox
* radio
* file
* button
* hidden
* password
* datetime (via jquery / javascript)
* nodisplay `will not create the input`
* max_len `Place an input restriction on the number of characters that can be entered into a text input`
* id `places a value in the id selector for the input`
* after `generated text directly to the right of the input`
* readonly `makes the input readonly`
* yesno `creates a simply radio option`

Input validator options available:  
`(All GUMP options currently available)`
* required
* max_len

# How to contribute

# TODO:
* finish off elements
* build array to sort elements








	 
	 
	 
	 



