<?php
if ( class_exists( 'WP_Customize_Control' ) ) {

  class WP_Customize_Message_Control extends WP_Customize_Control {
    public $type = 'custom_message';
    /**
    * Render the control's content.
    */
    public function render_content() {
      ?>
      <div class="panel">
        <h3 class="label"><?php echo $this->label; ?></h3>
        <div>
          <?php echo $this->description; ?>
        </div>
      </div>
      <?php
    }
  }

}
