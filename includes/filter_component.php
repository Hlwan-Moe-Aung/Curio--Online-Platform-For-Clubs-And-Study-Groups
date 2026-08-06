<?php 
    $search_key = $filter_config['search_key'] ?? 'search';
    $current_id = $_GET['id'] ?? '';
?>

<form method="GET" action="<?php echo $filter_config['action']; ?>" class="filter-bar">
    <?php if($current_id): ?>
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($current_id); ?>">
    <?php endif; ?>

    <input type="text" name="<?php echo $search_key; ?>" 
           placeholder="<?php echo $filter_config['placeholder']; ?>" 
           value="<?php echo htmlspecialchars($_GET[$search_key] ?? ''); ?>" class="filter-input">

    <?php 
    // FIX: Added '?? []' to handle cases where no dropdowns are defined
    foreach (($filter_config['dropdowns'] ?? []) as $name => $options): 
    ?>
        <select name="<?php echo $name; ?>" class="filter-input">
            <option value=""><?php echo ucfirst($name); ?></option>
            <?php foreach ($options as $value => $label): ?>
                <option value="<?php echo $value; ?>" <?php echo (isset($_GET[$name]) && $_GET[$name] == $value) ? 'selected' : ''; ?>>
                    <?php echo $label; ?>
                </option>
            <?php endforeach; ?>
        </select>
    <?php endforeach; ?>

    <?php if (isset($filter_config['show_date']) && $filter_config['show_date']): ?>
        <input type="date" name="date" class="filter-input" 
               value="<?php echo htmlspecialchars($_GET['date'] ?? ''); ?>">
    <?php endif; ?>

    <button type="submit" class="btn">Apply</button>
    
    <?php 
    // Only show Clear if there is a search or a dropdown selected (excluding ID)
    $has_filters = false;
    foreach($_GET as $key => $val) {
        if ($key !== 'id' && !empty($val)) {
            $has_filters = true;
            break;
        }
    }
    if($has_filters): 
    ?>
        <a href="<?php echo explode('?', $filter_config['action'])[0] . '?id=' . $current_id; ?>" class="clear-btn" style="text-decoration: none; margin-left: 10px; color: #666;">Clear</a>
    <?php endif; ?>
</form>