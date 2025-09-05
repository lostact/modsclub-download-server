import os, json, subprocess, requests, datetime, time

def is_updated_within_hour(path):
    try:
        modified_dir = subprocess.check_output("find {} -type d -mmin -60 -print -quit".format(path), shell=True, text=True)
        return bool(modified_dir)
    except Exception as e:
        print(f"An error occurred: {e}")
        return False

def human_readable_size(size, decimal_places=2):
    for unit in ['B', 'KB', 'MB', 'GB', 'TB']:
        if size < 1024.0:
            break
        size /= 1024.0
    return f"{size:.{decimal_places}f} {unit}"

def disk_usage_dict(path, usage_json_path):
    disk_usage_data = {}
    if os.path.isfile(usage_json_path):
        with open(usage_json_path, 'r') as usage_json:
            disk_usage_data = json.load(usage_json)

    for game_dir in os.scandir(path):
        if game_dir.is_dir():
            for mod_dir in os.scandir(game_dir):
                file_id = mod_dir.name
                #print(mod_dir.path)
                if (is_updated_within_hour(mod_dir.path)) or file_id not in disk_usage_data:
                    incomplete_zip_path = "/var/www/html/compressed/{}_incomplete.zip".format(file_id)
                    if os.path.exists(incomplete_zip_path):
                        continue;
                    zip_path = "/var/www/html/compressed/{}.zip".format(file_id)
                    if os.path.exists(zip_path):
                        os.remove(zip_path)
                    size = int(subprocess.check_output(['du','-sb', "--exclude='.git'", mod_dir]).split()[0].decode('utf-8'))
                    disk_usage_data[file_id] = {'size': size, 'size_human': human_readable_size(size), 'updated': int(time.time())}
                    print("updated: ", file_id, disk_usage_data[file_id])
                elif 'updated' not in disk_usage_data[file_id]:
                    print("asd")
                    updated = subprocess.check_output("find {}".format(mod_dir.path) + ' -type d -exec stat -c %Y {} + | sort -nr | head -n 1', shell=True, text=True)
                    disk_usage_data[file_id]['updated'] = int(updated)
                #print(file_id, disk_usage_data[file_id])
    return disk_usage_data

def save_dict_to_file(dictionary, filepath):
    json.dump(dictionary, open(filepath, 'w'))

if __name__ == '__main__':
    games_path = 'workshop'
    usage_json_path = 'disk_usage.json'

    disk_usage = disk_usage_dict(games_path, usage_json_path)
    save_dict_to_file(disk_usage, usage_json_path)
