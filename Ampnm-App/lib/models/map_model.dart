class MapModel {
  final int id;
  final String name;
  final String? backgroundColor;
  final String? backgroundImageUrl;
  final bool publicViewEnabled;

  MapModel({
    required this.id,
    required this.name,
    this.backgroundColor,
    this.backgroundImageUrl,
    this.publicViewEnabled = false,
  });

  factory MapModel.fromJson(Map<String, dynamic> json) {
    return MapModel(
      id: int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      name: json['name']?.toString() ?? 'Untitled Map',
      backgroundColor: json['background_color']?.toString(),
      backgroundImageUrl: json['background_image_url']?.toString(),
      publicViewEnabled: json['public_view_enabled'] == true ||
          json['public_view_enabled'] == '1' ||
          json['public_view_enabled'] == 1,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'background_color': backgroundColor,
      'background_image_url': backgroundImageUrl,
      'public_view_enabled': publicViewEnabled ? 1 : 0,
    };
  }
}
