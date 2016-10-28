<p> 
	<?php echo get_string('allowsubmissionsfromdate', 'onlinejudge'); ?>
	: 
	<?php echo date('Y-m-d h:i:s a', $onlinejudge->allowsubmissionsfromdate); ?>
</p>
<p> 
	<?php echo get_string('duedate', 'onlinejudge'); ?>
	: 
	<?php echo date('Y-m-d h:i:s a', $onlinejudge->duedate); ?>
</p>
<p> 
	<?php echo get_string('cutoffdate', 'onlinejudge'); ?>
	: 
	<?php echo date('Y-m-d h:i:s a', $onlinejudge->cutoffdate); ?>
</p>