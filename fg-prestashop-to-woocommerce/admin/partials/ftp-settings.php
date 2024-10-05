				<tr class="ftp_parameters">
					<th scope="row" colspan="2"><h3><?php _e('PrestaShop FTP parameters', 'fg-prestashop-to-woocommerce'); ?></h3></th>
				</tr>
				<tr class="ftp_parameters">
					<td scope="row" colspan="2"><?php _e("Used for the downloadable products or if you choose the FTP download method. You can leave these parameters empty otherwise.", 'fg-prestashop-to-woocommerce'); ?></td>
				</tr>
				<tr class="ftp_parameters">
					<th scope="row"><label for="ftp_host"><?php _e('FTP host', 'fg-prestashop-to-woocommerce'); ?></label></th>
					<td><input id="ftp_host" name="ftp_host" type="text" size="50" value="<?php echo esc_attr($data['ftp_host']); ?>" /></td>
				</tr>
				<tr class="ftp_parameters">
					<th scope="row"><label for="ftp_port"><?php _e('FTP port', 'fg-prestashop-to-woocommerce'); ?></label></th>
					<td><input id="ftp_port" name="ftp_port" type="text" size="50" value="<?php echo esc_attr($data['ftp_port']); ?>" /></td>
				</tr>
				<tr class="ftp_parameters">
					<th scope="row"><label for="ftp_login"><?php _e('FTP login', 'fg-prestashop-to-woocommerce'); ?></label></th>
					<td><input id="ftp_login" name="ftp_login" type="text" size="50" value="<?php echo esc_attr($data['ftp_login']); ?>" /></td>
				</tr>
				<tr class="ftp_parameters">
					<th scope="row"><label for="ftp_password"><?php _e('FTP password', 'fg-prestashop-to-woocommerce'); ?></label></th>
					<td><input id="ftp_password" name="ftp_password" type="password" size="50" value="<?php echo esc_attr($data['ftp_password']); ?>" /></td>
				</tr>
				<tr class="ftp_parameters">
					<th scope="row"><?php _e('Protocol', 'fg-prestashop-to-woocommerce'); ?></th>
					<td>
						<input id="ftp_connection_type_ftp" name="ftp_connection_type" type="radio" value="ftp" <?php checked($data['ftp_connection_type'], 'ftp'); ?> /> <label for="ftp_connection_type_ftp" ><?php _e('FTP', 'fg-prestashop-to-woocommerce'); ?></label>&nbsp;
						<input id="ftp_connection_type_ftps" name="ftp_connection_type" type="radio" value="ftps" <?php checked($data['ftp_connection_type'], 'ftps'); ?> /> <label for="ftp_connection_type_ftps" ><?php _e('FTPS', 'fg-prestashop-to-woocommerce'); ?></label>&nbsp;
						<input id="ftp_connection_type_sftp" name="ftp_connection_type" type="radio" value="sftp" <?php checked($data['ftp_connection_type'], 'sftp'); ?> /> <label for="ftp_connection_type_sftp" ><?php _e('SFTP', 'fg-prestashop-to-woocommerce'); ?></label> <small><?php printf(__('(SFTP requires the <a href="%s" target="_blank">WP Filesystem SSH2</a> plugin)', 'fg-prestashop-to-woocommerce'), 'https://www.fredericgilles.net/wp-filesystem-ssh2/'); ?></small>
					</td>
				</tr>
				<tr class="ftp_parameters">
					<th scope="row"><label for="ftp_dir"><?php _e('FTP base directory', 'fg-prestashop-to-woocommerce'); ?></label></th>
					<td><input id="ftp_dir" name="ftp_dir" type="text" size="50" value="<?php echo esc_attr($data['ftp_dir']); ?>" /></td>
				</tr>
				<tr class="ftp_parameters">
					<th scope="row">&nbsp;</th>
					<td>
						<div class="submit_button_with_spinner">
							<?php submit_button( __('Test the FTP connection', 'fg-prestashop-to-woocommerce'), 'secondary', 'test_ftp', false ); ?>
							<span id="ftp_test_spinner" class="spinner"></span>
						</div>
						<div id="ftp_test_message" class="action_message"></div>
					</td>
				</tr>
