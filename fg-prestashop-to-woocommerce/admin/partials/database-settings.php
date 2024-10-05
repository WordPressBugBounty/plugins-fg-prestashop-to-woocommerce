				<tr>
					<th scope="row" colspan="2"><h3><?php _e('PrestaShop database parameters', 'fg-prestashop-to-woocommerce'); ?></h3></th>
				</tr>
				<tr>
					<th scope="row"><label for="hostname"><?php _e('Hostname', 'fg-prestashop-to-woocommerce'); ?></label></th>
					<td><input id="hostname" name="hostname" type="text" size="50" value="<?php echo esc_attr($data['hostname']); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="port"><?php _e('Port', 'fg-prestashop-to-woocommerce'); ?></label></th>
					<td><input id="port" name="port" type="text" size="50" value="<?php echo esc_attr($data['port']); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="database"><?php _e('Database', 'fg-prestashop-to-woocommerce'); ?></label></th>
					<td><input id="database" name="database" type="text" size="50" value="<?php echo esc_attr($data['database']); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="username"><?php _e('Username', 'fg-prestashop-to-woocommerce'); ?></label></th>
					<td><input id="username" name="username" type="text" size="50" value="<?php echo esc_attr($data['username']); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="password"><?php _e('Password', 'fg-prestashop-to-woocommerce'); ?></label></th>
					<td><input id="password" name="password" type="password" size="50" value="<?php echo esc_attr($data['password']); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="prefix"><?php _e('PrestaShop Table Prefix', 'fg-prestashop-to-woocommerce'); ?></label></th>
					<td><input id="prefix" name="prefix" type="text" size="50" value="<?php echo esc_attr($data['prefix']); ?>" /></td>
				</tr>
