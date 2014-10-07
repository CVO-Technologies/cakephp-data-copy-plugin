<strong><?php echo h(__d('data_copy', 'Expired lookup\'s:')); ?></strong> <?php echo h(count($content['expired'])); ?>
<br>
<strong><?php echo h(__d('data_copy', 'Copied lookup\'s:')); ?></strong> <?php echo h(count($content['copied'])); ?><br>

<table>
	<caption><?php echo h(__d('data_copy', 'Expired lookup\'s')); ?></caption>
	<thead>
	<tr>
		<th><?php echo h(__d('data_copy', 'Model')); ?></th>
		<th><?php echo h(__d('data_copy', 'Origin model')); ?></th>
		<th><?php echo h(__d('data_copy', 'Reason')); ?></th>
		<th><?php echo h(__d('data_copy', 'Query')); ?></th>
	</tr>
	</thead>
	<tbody>
	<?php foreach ($content['expired'] as $lookup): ?>
		<tr>
			<td><?php echo h($lookup['model']->alias); ?></td>
			<td><?php echo h($lookup['origin']->alias); ?></td>
			<td><?php echo h($lookup['reason']); ?></td>
			<td><?php echo $this->Toolbar->makeNeatArray($lookup['query']); ?></td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>

<table>
	<caption><?php echo h(__d('data_copy', 'Copied lookup\'s')); ?></caption>
	<thead>
	<tr>
		<th><?php echo h(__d('data_copy', 'Model')); ?></th>
		<th><?php echo h(__d('data_copy', 'Origin model')); ?></th>
		<th><?php echo h(__d('data_copy', 'Query')); ?></th>
	</tr>
	</thead>
	<tbody>
	<?php foreach ($content['copied'] as $lookup): ?>
		<tr>
			<td><?php echo h($lookup['model']->alias); ?></td>
			<td><?php echo h($lookup['origin']->alias); ?></td>
			<td><?php echo $this->Toolbar->makeNeatArray($lookup['query']); ?></td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>