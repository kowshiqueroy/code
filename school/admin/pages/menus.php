<?php // admin/pages/menus.php
$db=getDB();
if($_SERVER['REQUEST_METHOD']==='POST'){
    $action=$_POST['menu_action']??'';
    if($action==='add'){$stmt=$db->prepare("INSERT INTO menus (parent_id,title_en,title_bn,page_slug,url,sort_order,is_active,menu_location) VALUES (?,?,?,?,?,?,?,?)");$stmt->execute([(int)$_POST['parent_id'],sanitize($_POST['title_en']??''),sanitize($_POST['title_bn']??''),sanitize($_POST['page_slug']??''),sanitize($_POST['url']??''),(int)($_POST['sort_order']??0),isset($_POST['is_active'])?1:0,sanitize($_POST['menu_location']??'main')]);flash('Menu added.','success');}
    elseif($action==='delete'&&isset($_POST['menu_id'])){$db->prepare("DELETE FROM menus WHERE id=?")->execute([(int)$_POST['menu_id']]);flash('Deleted.','success');}
    redirect(ADMIN_PATH.'?section=menus');
}
$menus=$db->query("SELECT * FROM menus ORDER BY parent_id,sort_order")->fetchAll();
$parents=array_filter($menus,fn($m)=>$m['parent_id']==0);
?>
<div class="acard">
  <div class="acard-header"><div class="acard-title">☰ Menu Manager</div></div>
  <div class="acard-body">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start">
      <!-- Current menus -->
      <div>
        <h3 style="margin-bottom:12px;font-size:.9rem;font-weight:700">Current Menus</h3>
        <table class="atable">
          <thead><tr><th>Title</th><th>Slug/URL</th><th>Parent</th><th>Sort</th><th></th></tr></thead>
          <tbody>
            <?php foreach($menus as $m):
              $parentName='Root';
              if($m['parent_id']>0){foreach($menus as $pm){if($pm['id']==$m['parent_id']){$parentName=mb_substr($pm['title_en'],0,15);break;}}}
            ?>
            <tr>
              <td><?= $m['parent_id']>0?'&nbsp;&nbsp;└ ':'' ?><?= h($m['title_en']) ?><br><small style="color:#888"><?= h($m['title_bn']) ?></small></td>
              <td><small><?= h($m['page_slug']?:'('.$m['url'].')') ?></small></td>
              <td><small><?= h($parentName) ?></small></td>
              <td><?= h($m['sort_order']) ?></td>
              <td><form method="POST" style="display:inline"><input type="hidden" name="menu_action" value="delete"><input type="hidden" name="menu_id" value="<?= $m['id'] ?>"><button class="btn btn-xs btn-danger" data-confirm="Delete?">🗑️</button></form></td>
            </tr>
            <?php endforeach;?>
          </tbody>
        </table>
      </div>
      <!-- Add menu -->
      <div>
        <h3 style="margin-bottom:12px;font-size:.9rem;font-weight:700">Add Menu Item</h3>
        <form method="POST" class="aform">
          <input type="hidden" name="menu_action" value="add">
          <div class="form-group"><label>Title (English) <span class="req">*</span></label><input type="text" name="title_en" required></div>
          <div class="form-group"><label>শিরোনাম (বাংলা)</label><input type="text" name="title_bn"></div>
          <div class="form-group"><label>Page Slug <span class="hint">(e.g. about or academic&sub=routine)</span></label><input type="text" name="page_slug" placeholder="about"></div>
          <div class="form-group"><label>Or Custom URL</label><input type="url" name="url" placeholder="https://..."></div>
          <div class="form-group"><label>Parent (0 = top level)</label>
            <select name="parent_id">
              <option value="0">— Top Level —</option>
              <?php foreach($parents as $p):?><option value="<?= $p['id'] ?>"><?= h($p['title_en']) ?></option><?php endforeach;?>
            </select>
          </div>
          <div class="form-row">
            <div class="form-group"><label>Sort Order</label><input type="number" name="sort_order" value="0" min="0"></div>
            <div class="form-group"><label>Location</label><select name="menu_location"><option value="main">Main Nav</option><option value="footer">Footer</option></select></div>
          </div>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-bottom:16px"><input type="checkbox" name="is_active" checked> Active</label>
          <button type="submit" class="btn btn-primary">+ Add Menu Item</button>
        </form>
      </div>
    </div>
  </div>
</div>
