/**
 * Catheo API REST V1 - Exhaustive TypeScript Data Transfer Objects (DTOs) & Form Models
 * Conforme à 100% au prototype officiel Cathéo Admin
 * Compatible Angular 17+ / RxJS / Reactive Forms
 */

// ==========================================
// 0. GLOBAL API GENERIC RESPONSE WRAPPERS
// ==========================================

export interface ApiResponse<T> {
  status: 'success' | 'error';
  message?: string;
  data: T;
  kpis?: Record<string, any>;
  meta?: PaginationMeta;
}

export interface ErrorResponseDto {
  status: 'error';
  message: string;
}

export interface ValidationErrorResponseDto {
  status: 'error';
  message: string;
  errors: Record<string, string[]>;
}

export interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

// ==========================================
// 1. AUTHENTIFICATION & COMPTES UTILISATEURS
// ==========================================

export interface LoginRequestDto {
  login?: string;
  email?: string;
  password: string;
  device_name?: string;
}

export interface AdminLoginRequestDto {
  login: string; // Email, Téléphone ou Username
  password: string;
  device_name?: string;
}

export interface AnimateurLoginRequestDto {
  login: string; // Téléphone, Email ou Matricule
  password: string;
  device_name?: string;
}

export interface ParentLoginRequestDto {
  login: string; // Code Catéchumène (Matricule) ou Téléphone Parent/Tuteur
  password: string; // Mot de passe ou Code PIN d'accès
  device_name?: string;
}

export interface RefreshTokenRequestDto {
  device_name?: string;
}

export interface ChangePasswordRequestDto {
  current_password: string;
  password: string;
  password_confirmation: string;
}

export interface ForgotPasswordRequestDto {
  email: string;
}

export interface VerifyResetCodeRequestDto {
  email: string;
  code: string;
}

export interface ResetPasswordRequestDto {
  email: string;
  code: string;
  password: string;
  password_confirmation: string;
  device_name?: string;
}

export interface LoginResponseDto {
  token: string;
  token_type: string;
  user_type?: 'admin' | 'animateur' | 'parent';
  user: UserDto;
  annee_courante?: AnneeCatecheseDto | null;
  menus?: AccessibleMenuDto[];
}

export interface AdminLoginResponseDto {
  token: string;
  token_type: string;
  user_type: 'admin';
  user: UserDto;
  annee_courante?: AnneeCatecheseDto | null;
  menus?: AccessibleMenuDto[];
}

export interface AnimateurAuthDto {
  id: string;
  matricule?: string;
  nom: string;
  prenoms: string;
  sexe: string;
  telephone?: string;
  email?: string;
  profession?: string;
  statut: string;
  created_at?: string;
}

export interface AnimateurLoginResponseDto {
  token: string;
  token_type: string;
  user_type: 'animateur';
  user: AnimateurAuthDto;
  menus?: AccessibleMenuDto[];
}

export interface CatechumeneParentAuthDto {
  id: string;
  matricule: string;
  code_catechumene?: string;
  nom: string;
  prenoms: string;
  sexe: 'M' | 'F';
  date_naissance: string;
  telephone?: string;
  nom_pere?: string;
  telephone_pere?: string;
  nom_mere?: string;
  telephone_mere?: string;
  nom_tuteur?: string;
  telephone_tuteur?: string;
  est_baptise: boolean;
  statut: string;
  created_at?: string;
}

export interface ParentLoginResponseDto {
  token: string;
  token_type: string;
  user_type: 'parent';
  user: CatechumeneParentAuthDto;
  menus?: AccessibleMenuDto[];
}

export interface UserDto {
  id: string;
  name: string;
  email: string;
  telephone?: string;
  statut: 'actif' | 'inactif' | 'suspendu';
  profil?: ProfilDto;
  catechese?: CatecheseConfigurationDto;
  paroisse?: ParoisseConfigurationDto;
  created_at: string;
}

export interface CreateUserDto {
  name: string;
  email: string;
  password: string;
  telephone?: string;
  profil_id: string;
}

export interface UpdateUserDto {
  name?: string;
  email?: string;
  password?: string;
  telephone?: string;
  profil_id?: string;
}

export interface UpdateUserStatusDto {
  statut: 'actif' | 'inactif' | 'suspendu';
}

// --- MENUS & PERMISSIONS MATRICE ---

export interface MenuActionFlagsDto {
  create: boolean;
  read: boolean;
  update: boolean;
  delete: boolean;
  restore: boolean;
  force_delete: boolean;
}

export interface MenuDto {
  id: string;
  uuid: string;
  libelle: string;
  icon?: string;
  path?: string;
  reference: string;
  ordre: number;
  is_active: boolean;
  parent_id?: string;
  sousMenus?: MenuDto[];
}

export interface AccessibleMenuDto {
  uuid: string;
  libelle: string;
  icon?: string;
  path?: string;
  reference: string;
  ordre: number;
  permissions: MenuActionFlagsDto;
  sousMenus?: AccessibleMenuDto[];
}

export interface ProfilMenuPermissionDto {
  uuid?: string;
  profil_id: string;
  menu_id: string;
  can_read: boolean;
  can_create: boolean;
  can_update: boolean;
  can_delete: boolean;
  can_restore: boolean;
  can_force_delete: boolean;
}

export interface ProfilDto {
  id: string;
  uuid?: string;
  nom: string;
  code: string;
  description?: string;
  statut?: 'Actif' | 'Inactif' | 'actif' | 'inactif';
  statut_code?: 'actif' | 'inactif';
  permissions: string[];
  total_permissions?: number;
  is_system: boolean;
  users_count?: number;
  menus?: AccessibleMenuDto[];
  created_at?: string;
}

export interface PermissionItemDto {
  key: string;
  label: string;
}

export interface SubMenuPermissionDto {
  nom: string;
  code: string;
  permissions: PermissionItemDto[];
}

export interface PermissionTreeNodeDto {
  menu: string;
  code: string;
  permissions?: PermissionItemDto[];
  sous_menus?: SubMenuPermissionDto[];
}

export interface CreateProfilDto {
  nom: string;
  description?: string;
  permissions: string[];
}

export interface UpdateProfilDto {
  nom?: string;
  description?: string;
  permissions?: string[];
}

// ==========================================
// 2. CONFIGURATION DE LA CATECHESE (INSTITUTIONNELLE)
// ==========================================

export interface CatecheseConfigurationDto {
  id: string;
  nom_paroisse: string;
  nom?: string; // Rétro-compatibilité
  code_paroisse: string;
  prefixe_matricule?: string;
  prefixe_recu?: string;
  diocese?: string;
  doyenne?: string;
  ville?: string;
  commune?: string;
  telephone?: string;
  email?: string;
  site_web?: string;
  adresse?: string;
  logo_paroisse?: string;
  logo_paroisse_url?: string;
  logo_catechese?: string;
  logo_catechese_url?: string;
  logo_url?: string; // Rétro-compatibilité
  cure_nom?: string;
  coordination_nom?: string;
  statut: 'actif' | 'inactif' | 'suspendu';
  created_at?: string;
}

export interface UpdateCatecheseConfigurationDto {
  nom_paroisse?: string;
  nom?: string;
  code_paroisse?: string;
  prefixe_matricule?: string;
  prefixe_recu?: string;
  diocese?: string;
  doyenne?: string;
  ville?: string;
  commune?: string;
  telephone?: string;
  email?: string;
  site_web?: string;
  adresse?: string;
  cure_nom?: string;
  coordination_nom?: string;
  statut?: 'actif' | 'inactif' | 'suspendu';
  logo_paroisse?: File | string | null;
  logo_catechese?: File | string | null;
  logo?: File | string | null;
}

// Alias de rétro-compatibilité
export type ParoisseConfigurationDto = CatecheseConfigurationDto;
export type UpdateParoisseConfigurationDto = UpdateCatecheseConfigurationDto;

export interface ApparenceConfigurationDto {
  id: string;
  couleur_principale: string; // Ex: "#4F46E5"
  couleur_secondaire: string; // Ex: "#D97706"
  police_caracteres: 'Inter' | 'Roboto' | 'Outfit' | 'Poppins' | 'Nunito' | 'DM Sans';
  entete_document?: string;
  pied_page_document?: string;
  created_at?: string;
  updated_at?: string;
  created_by?: string;
  updated_by?: string;
}

export interface UpdateApparenceConfigurationDto {
  couleur_principale?: string;
  couleur_secondaire?: string;
  police_caracteres?: 'Inter' | 'Roboto' | 'Outfit' | 'Poppins' | 'Nunito' | 'DM Sans';
  entete_document?: string;
  pied_page_document?: string;
}

export interface SauvegardeDto {
  id: string;
  nom_fichier: string;
  date: string; // Ex: "10/08/2026"
  heure: string; // Ex: "00:46"
  taille: string; // Ex: "13.1 MB"
  taille_octets: number;
  cree_par: string; // Ex: "Abbé Ferdinand" ou "Système (Auto)"
  type: 'manuel' | 'automatique';
  statut: 'termine' | 'en_cours' | 'echec';
  created_at?: string;
}

export interface ResponsableCatecheseDto {
  id: string;
  nom_prenoms: string;
  fonction: string;
  telephone?: string;
  statut: 'actif' | 'inactif';
  created_at?: string;
  updated_at?: string;
  created_by?: string;
  updated_by?: string;
}

export interface CreateResponsableCatecheseDto {
  nom_prenoms: string;
  fonction: string;
  telephone?: string;
  statut?: 'actif' | 'inactif';
}

export interface UpdateResponsableCatecheseDto {
  nom_prenoms?: string;
  fonction?: string;
  telephone?: string;
  statut?: 'actif' | 'inactif';
}

// ==========================================
// 3. ORGANISATION PASTORALE & CALENDRIER
// ==========================================

export interface AnneeCatecheseDto {
  id: string;
  libelle: string;
  date_debut: string;
  date_fin: string;
  statut: 'preparation' | 'active' | 'cloturee';
  created_at?: string;
  updated_at?: string;
}

export interface CreateAnneeCatecheseDto {
  libelle: string;
  date_debut: string;
  date_fin: string;
  statut?: 'preparation' | 'active' | 'cloturee';
}

export interface UpdateAnneeCatecheseDto extends Partial<CreateAnneeCatecheseDto> {}

export interface SectionDto {
  id: string;
  nom: string;
  code?: string;
  description?: string;
  statut: 'Actif' | 'Inactif' | 'actif' | 'inactif';
  statut_code: 'actif' | 'inactif';
  ordre_affichage?: number;
  niveaux_count?: number;
}

export interface CreateSectionDto {
  nom: string;
  code: string;
  description?: string;
  statut?: 'actif' | 'inactif';
  ordre_affichage?: number;
}

export interface UpdateSectionDto {
  nom?: string;
  code?: string;
  description?: string;
  statut?: 'actif' | 'inactif';
  ordre_affichage?: number;
}

export interface NiveauDto {
  id: string;
  nom: string;
  description?: string;
  statut: 'Actif' | 'Inactif' | 'actif' | 'inactif';
  statut_code: 'actif' | 'inactif';
  ordre_affichage?: number;
  section?: SectionDto;
}

export interface CreateNiveauDto {
  section_id: string;
  nom: string;
  description?: string;
  statut?: 'actif' | 'inactif';
  ordre_affichage?: number;
}

export interface UpdateNiveauDto {
  section_id?: string;
  nom?: string;
  description?: string;
  statut?: 'actif' | 'inactif';
  ordre_affichage?: number;
}

export interface ClasseDto {
  id: string;
  nom: string;
  capacite_max: number;
  statut: 'active' | 'inactive';
  niveau?: NiveauDto;
  annee_catechese?: AnneeCatecheseDto;
  effectif_actuel?: number;
}

export interface CreateClasseDto {
  niveau_id: string;
  annee_catechese_id?: string;
  nom: string;
  capacite_max?: number;
}

export interface UpdateClasseDto extends Partial<CreateClasseDto> {
  statut?: 'active' | 'inactive';
}

export interface AnimateurDto {
  id: string;
  matricule?: string;
  nom: string;
  prenoms: string;
  nom_complet?: string;
  sexe: 'M' | 'F';
  telephone?: string | null;
  email?: string | null;
  profession?: string | null;
  statut: 'actif' | 'inactif';
  user?: UserDto;
  dernier_login_at?: string | null;
  affectations_count?: number;
  created_at?: string;
  updated_at?: string;
}

export interface CreateAnimateurDto {
  nom: string;
  prenoms: string;
  sexe: 'M' | 'F';
  telephone?: string;
  email?: string;
  profession?: string;
  statut?: 'actif' | 'inactif';
  password?: string;
  create_user_account?: boolean;
}

export interface UpdateAnimateurDto {
  nom?: string;
  prenoms?: string;
  sexe?: 'M' | 'F';
  telephone?: string;
  email?: string;
  profession?: string;
  statut?: 'actif' | 'inactif';
  password?: string;
}

export interface UpdateAnimateurStatusDto {
  statut: 'actif' | 'inactif';
}

export interface LoginAnimateurDto {
  login: string; // telephone ou email
  password: string;
}

export interface AffectationAnimateurDto {
  id: string;
  role?: 'principal' | 'adjoint' | 'assistant';
  role_animateur?: 'principal' | 'adjoint';
  animateur?: AnimateurDto;
  classe?: ClasseDto;
  annee_catechese?: AnneeCatecheseDto;
  date_affectation?: string;
  created_at?: string;
}

export interface CreateAffectationAnimateurDto {
  animateur_id: string;
  annee_catechese_id?: string;
  classe_id: string;
  role?: 'principal' | 'adjoint' | 'assistant';
  role_animateur?: 'principal' | 'adjoint';
}

export interface UpdateAffectationAnimateurDto {
  animateur_id?: string;
  annee_catechese_id?: string;
  classe_id?: string;
  role_animateur?: 'principal' | 'adjoint';
}

export interface ModuleTrimestrielDto {
  id: string;
  trimestre?: 'T1' | 'T2' | 'T3';
  numero_trimestre?: number;
  nom?: string;
  libelle: string;
  date_debut: string;
  date_fin: string;
  statut?: 'en_cours' | 'termine';
  annee_catechese?: AnneeCatecheseDto;
  created_at?: string;
}

export interface CreateModuleTrimestrielDto {
  annee_catechese_id?: string;
  trimestre?: 'T1' | 'T2' | 'T3';
  numero_trimestre?: number;
  nom?: string;
  libelle: string;
  date_debut: string;
  date_fin: string;
  statut?: 'en_cours' | 'termine';
}

export interface UpdateModuleTrimestrielDto {
  annee_catechese_id?: string;
  trimestre?: 'T1' | 'T2' | 'T3';
  numero_trimestre?: number;
  nom?: string;
  libelle?: string;
  date_debut?: string;
  date_fin?: string;
  statut?: 'en_cours' | 'termine';
}

// ──────────────────────────────────────────
// CEB (Communautés Ecclésiales de Base)
// ──────────────────────────────────────────
export interface CebDto {
  id: string;
  nom: string;
  responsable?: string | null;
  telephone?: string | null;
  adresse?: string | null;
  description?: string | null;
  statut: 'Active' | 'Inactive';
  statut_code: 'active' | 'inactive';
  total_inscriptions?: number;
  created_at?: string;
}

export interface CreateCebDto {
  nom: string;
  responsable?: string;
  telephone?: string;
  adresse?: string;
  description?: string;
  statut?: 'Active' | 'Inactive' | 'active' | 'inactive';
}

export interface UpdateCebDto {
  nom?: string;
  responsable?: string;
  telephone?: string;
  adresse?: string;
  description?: string;
  statut?: 'Active' | 'Inactive' | 'active' | 'inactive';
}

// ──────────────────────────────────────────
// Mouvements Paroissiaux
// ──────────────────────────────────────────
export interface MouvementDto {
  id: string;
  nom: string;
  responsable?: string | null;
  telephone?: string | null;
  description?: string | null;
  statut: 'Active' | 'Inactive';
  statut_code: 'active' | 'inactive';
  total_inscriptions?: number;
  created_at?: string;
}

export interface CreateMouvementDto {
  nom: string;
  responsable?: string;
  telephone?: string;
  description?: string;
  statut?: 'Active' | 'Inactive' | 'active' | 'inactive';
}

export interface UpdateMouvementDto {
  nom?: string;
  responsable?: string;
  telephone?: string;
  description?: string;
  statut?: 'Active' | 'Inactive' | 'active' | 'inactive';
}

// ──────────────────────────────────────────
// Calendrier Pastoral
// ──────────────────────────────────────────
export type CibleTypeCalendrier =
  | 'TOUS'
  | 'Tous'
  | 'ANIMATEURS'
  | 'Animateurs'
  | 'Catéchumènes'
  | 'SECTION'
  | 'Section'
  | 'NIVEAU'
  | 'Niveau'
  | 'CLASSE'
  | 'Classe'
  | 'CEB'
  | 'MOUVEMENT'
  | string;

export type StatutCalendrier = 'Planifié' | 'Réalisé' | 'Annulé' | 'planifie' | 'realise' | 'annule';

export interface CalendrierDto {
  id: string;
  annee_catechese_id?: string;
  titre: string;
  type: string;
  date: string;
  heure_debut?: string | null;
  heure_fin?: string | null;
  lieu?: string | null;
  cible_type: CibleTypeCalendrier;
  cible_id?: string | null;
  cible_ids?: string[] | null;
  cible_nom?: string | null;
  description?: string | null;
  statut: 'Planifié' | 'Réalisé' | 'Annulé' | string;
  annee_catechese?: AnneeCatecheseDto;
  created_at?: string;
  updated_at?: string;
}

export interface CreateCalendrierDto {
  annee_catechese_id?: string;
  titre: string;
  type: string;
  date: string;
  heure_debut?: string;
  heure_fin?: string;
  lieu?: string;
  cible_type?: CibleTypeCalendrier;
  cible_id?: string;
  cible_ids?: string[];
  cible_nom?: string;
  description?: string;
  statut?: StatutCalendrier;
}

export interface UpdateCalendrierDto {
  annee_catechese_id?: string;
  titre?: string;
  type?: string;
  date?: string;
  heure_debut?: string;
  heure_fin?: string;
  lieu?: string;
  cible_type?: CibleTypeCalendrier;
  cible_id?: string;
  cible_ids?: string[];
  cible_nom?: string;
  description?: string;
  statut?: StatutCalendrier;
}

export interface UpdateCalendrierStatutDto {
  statut: 'Planifié' | 'Réalisé' | 'Annulé' | string;
}

// ==========================================
// 4. PREINSCRIPTIONS & CATÉCHUMÈNES
// ==========================================

export interface CampagnePreinscriptionDto {
  id: string;
  titre: string;
  nom?: string;
  date_debut: string;
  date_fin: string;
  statut: 'ouverte' | 'fermee' | 'suspendue';
  est_ouverte: boolean;
  description?: string;
  sections_autorisees?: string[];
  public_url?: string;
  qr_code_url?: string;
  annee_catechese?: AnneeCatecheseDto;
  preinscriptions_count?: number;
  created_at?: string;
}

export interface CreateCampagnePreinscriptionDto {
  annee_catechese_id: string;
  titre: string;
  date_debut: string;
  date_fin: string;
  sections_autorisees?: string[];
  description?: string;
  statut?: 'ouverte' | 'fermee' | 'suspendue';
}

export interface UpdateCampagnePreinscriptionDto {
  titre?: string;
  date_debut?: string;
  date_fin?: string;
  sections_autorisees?: string[];
  description?: string;
  statut?: 'ouverte' | 'fermee' | 'suspendue';
}

export type PreinscriptionStatus = 'en_attente' | 'validee' | 'rejetee' | 'a_affecter';
export type TypeDemandePreinscription = 'nouvelle_inscription' | 'reinscription' | 'premiere_inscription';

export interface PreinscriptionDto {
  id: string;
  uuid?: string;
  code_dossier: string;
  type_demande: TypeDemandePreinscription;
  statut: PreinscriptionStatus;
  nom: string;
  prenoms: string;
  nom_complet?: string;
  sexe: 'M' | 'F';
  date_naissance: string;
  lieu_naissance?: string;
  adresse?: string;
  telephone?: string;
  photo_url?: string;
  photo_profil?: string;
  situation_matrimoniale?: string;
  nom_pere?: string;
  telephone_pere?: string;
  nom_mere?: string;
  telephone_mere?: string;
  nom_tuteur?: string;
  telephone_tuteur?: string;
  est_baptise: boolean;
  date_bapteme?: string;
  lieu_bapteme?: string;
  paroisse_bapteme?: string;
  nom_parrain?: string;
  sexe_parrain?: 'M' | 'F';
  telephone_parrain?: string;
  acte_naissance_url?: string;
  notes_validation?: string;
  campagne_id?: string;
  campagne_preinscription_id?: string;
  annee_catechese_id?: string;
  section_souhaite_id?: string;
  niveau_souhaite_id?: string;
  campagne?: CampagnePreinscriptionDto | any;
  annee_catechese?: AnneeCatecheseDto | any;
  section_souhaite?: SectionDto | any;
  niveau_souhaite?: NiveauDto | any;
  created_at?: string;
  updated_at?: string;
}

export interface CreatePreinscriptionDto {
  campagne_id?: string;
  campagne_preinscription_id?: string;
  annee_catechese_id?: string;
  section_souhaite_id?: string;
  section_id?: string;
  niveau_souhaite_id?: string;
  niveau_id?: string;
  type_demande?: TypeDemandePreinscription;
  nom: string;
  prenoms: string;
  sexe: 'M' | 'F';
  date_naissance: string;
  lieu_naissance?: string;
  adresse?: string;
  telephone?: string;
  photo_url?: string;
  situation_matrimoniale?: string;
  nom_pere?: string;
  telephone_pere?: string;
  nom_mere?: string;
  telephone_mere?: string;
  nom_tuteur?: string;
  telephone_tuteur?: string;
  est_baptise?: boolean;
  date_bapteme?: string;
  lieu_bapteme?: string;
  paroisse_bapteme?: string;
  nom_parrain?: string;
  sexe_parrain?: 'M' | 'F';
  telephone_parrain?: string;
  acte_naissance_url?: string;
  statut?: PreinscriptionStatus;
  notes_validation?: string;
}

export interface SubmitPreinscriptionDto extends CreatePreinscriptionDto {}

export interface UpdatePreinscriptionDto extends Partial<CreatePreinscriptionDto> {}

export interface ValiderPreinscriptionDto {
  niveau_id: string;
  classe_id?: string;
  catechumene_id?: string;
  frais_payes?: boolean;
  notes_validation?: string;
}

export interface RejeterPreinscriptionDto {
  motif?: string;
}

export interface CatechumeneDto {
  id: string;
  matricule: string;
  nom: string;
  prenoms: string;
  nom_complet?: string;
  sexe: 'M' | 'F';
  date_naissance: string;
  lieu_naissance?: string;
  adresse?: string;
  domicile?: string;
  profession?: string;
  classe_scolaire?: string;
  situation_matrimoniale?: string;
  telephone?: string;
  photo_path?: string;
  photo_url?: string;
  nom_pere?: string;
  origine_pere?: string;
  telephone_pere?: string;
  nom_mere?: string;
  origine_mere?: string;
  telephone_mere?: string;
  nom_tuteur?: string;
  telephone_tuteur?: string;
  est_baptise: boolean;
  num_carnet_bapteme?: string;
  date_bapteme?: string;
  lieu_bapteme?: string;
  diocese_bapteme?: string;
  ville_bapteme?: string;
  paroisse_bapteme?: string;
  date_premiere_communion?: string;
  paroisse_premiere_communion?: string;
  date_confirmation?: string;
  paroisse_confirmation?: string;
  ministre_confirmation?: string;
  statut: 'actif' | 'abandon' | 'transfere' | 'complete';
  ceb?: CebDto;
  inscriptions_annuelles?: InscriptionAnnuelleDto[];
  parrains_marraines?: ParrainMarraineDto[];
  created_at: string;
}

export interface CreateCatechumeneDto {
  ceb_id?: string;
  nom: string;
  prenoms: string;
  sexe: 'M' | 'F';
  date_naissance: string;
  lieu_naissance?: string;
  adresse?: string;
  domicile?: string;
  profession?: string;
  classe_scolaire?: string;
  situation_matrimoniale?: string;
  telephone?: string;
  photo_url?: string;
  nom_pere?: string;
  origine_pere?: string;
  telephone_pere?: string;
  nom_mere?: string;
  origine_mere?: string;
  telephone_mere?: string;
  nom_tuteur?: string;
  telephone_tuteur?: string;
  est_baptise?: boolean;
  num_carnet_bapteme?: string;
  date_bapteme?: string;
  lieu_bapteme?: string;
  diocese_bapteme?: string;
  ville_bapteme?: string;
  paroisse_bapteme?: string;
  date_premiere_communion?: string;
  paroisse_premiere_communion?: string;
  date_confirmation?: string;
  paroisse_confirmation?: string;
  ministre_confirmation?: string;
  statut?: 'actif' | 'abandon' | 'transfere' | 'complete';
}

export interface UpdateCatechumeneDto {
  ceb_id?: string;
  nom?: string;
  prenoms?: string;
  sexe?: 'M' | 'F';
  date_naissance?: string;
  lieu_naissance?: string;
  adresse?: string;
  domicile?: string;
  profession?: string;
  classe_scolaire?: string;
  situation_matrimoniale?: string;
  telephone?: string;
  photo_url?: string;
  nom_pere?: string;
  origine_pere?: string;
  telephone_pere?: string;
  nom_mere?: string;
  origine_mere?: string;
  telephone_mere?: string;
  nom_tuteur?: string;
  telephone_tuteur?: string;
  est_baptise?: boolean;
  num_carnet_bapteme?: string;
  date_bapteme?: string;
  lieu_bapteme?: string;
  diocese_bapteme?: string;
  ville_bapteme?: string;
  paroisse_bapteme?: string;
  date_premiere_communion?: string;
  paroisse_premiere_communion?: string;
  date_confirmation?: string;
  paroisse_confirmation?: string;
  ministre_confirmation?: string;
  statut?: 'actif' | 'abandon' | 'transfere' | 'complete';
}

export interface InscriptionAnnuelleDto {
  id: string;
  code_inscription?: string;
  date_inscription: string;
  statut_inscription: 'inscrit' | 'valide' | 'en_attente' | 'abandon';
  frais_inscription_payes: boolean;
  observation?: string;
  catechumene?: CatechumeneDto;
  annee_catechese?: AnneeCatecheseDto;
  section?: SectionDto;
  niveau?: NiveauDto;
  classe?: ClasseDto;
  ceb?: CebDto;
  mouvement?: MouvementDto;
  created_at?: string;
}

export interface CreateInscriptionAnnuelleDto {
  catechumene_id: string;
  annee_catechese_id: string;
  section_id?: string;
  niveau_id: string;
  classe_id?: string;
  ceb_id?: string;
  mouvement_id?: string;
  date_inscription?: string;
  frais_inscription_payes?: boolean;
  observation?: string;
}

export interface UpdateInscriptionAnnuelleDto {
  section_id?: string;
  niveau_id?: string;
  classe_id?: string;
  ceb_id?: string;
  mouvement_id?: string;
  statut_inscription?: 'inscrit' | 'valide' | 'en_attente' | 'abandon';
  frais_inscription_payes?: boolean;
  observation?: string;
}

export interface ParrainMarraineDto {
  id: string;
  type: 'parrain' | 'marraine';
  nom_prenoms: string;
  telephone?: string;
  email?: string;
  domicile?: string;
  paroisse_origine?: string;
  representant_nom?: string;
  representant_contact?: string;
  sacrement_confirmation: boolean;
  catechumene?: CatechumeneDto;
  created_at?: string;
}

export interface CreateParrainMarraineDto {
  catechumene_id: string;
  type: 'parrain' | 'marraine';
  nom_prenoms: string;
  telephone?: string;
  email?: string;
  domicile?: string;
  paroisse_origine?: string;
  representant_nom?: string;
  representant_contact?: string;
  sacrement_confirmation?: boolean;
}

export interface UpdateParrainMarraineDto {
  type?: 'parrain' | 'marraine';
  nom_prenoms?: string;
  telephone?: string;
  email?: string;
  domicile?: string;
  paroisse_origine?: string;
  representant_nom?: string;
  representant_contact?: string;
  sacrement_confirmation?: boolean;
}

export interface MutationCatechumeneDto {
  id: string;
  catechumene_id?: string;
  annee_catechese_id?: string;
  matricule?: string;
  nom_complet?: string;
  paroisse_origine_nom: string;
  paroisse_destination_nom: string;
  motif?: string;
  date_mutation: string;
  statut: 'demande' | 'approuve' | 'refuse';
  catechumene?: CatechumeneDto;
  annee_catechese?: AnneeCatecheseDto;
  created_at?: string;
  updated_at?: string;
}

export interface CreateMutationCatechumeneDto {
  catechumene_id: string;
  annee_catechese_id?: string;
  paroisse_origine_nom?: string;
  paroisse_destination_nom: string;
  motif?: string;
  date_mutation?: string;
  statut?: 'demande' | 'approuve' | 'refuse';
}

export interface UpdateMutationCatechumeneDto {
  paroisse_origine_nom?: string;
  paroisse_destination_nom?: string;
  motif?: string;
  date_mutation?: string;
  statut?: 'demande' | 'approuve' | 'refuse';
}

// ==========================================
// 5. SÉANCES, PRÉSENCES & ÉVALUATIONS
// ==========================================

export type StatutSeance = 'planifiee' | 'effectuee' | 'annulee';
export type StatutPresence = 'present' | 'absent' | 'retard' | 'excuse';

export interface PresenceItemDto {
  id?: string;
  catechumene_id: string;
  catechumene?: CatechumeneDto;
  statut_presence?: StatutPresence;
  est_present?: boolean;
  remarque?: string;
  motif_absence?: string;
  created_at?: string;
  updated_at?: string;
}

export interface PresenceBatchItemDto {
  catechumene_id: string;
  statut_presence?: StatutPresence;
  est_present?: boolean;
  remarque?: string;
  motif_absence?: string;
}

export interface RecordPresencesBatchDto {
  presences: PresenceBatchItemDto[];
}

export interface SeanceDto {
  id: string;
  titre?: string;
  titre_lecon?: string;
  date_seance: string;
  heure_debut?: string;
  heure_fin?: string;
  duree_minutes?: number;
  description?: string;
  statut?: StatutSeance;
  annee_catechese_id?: string;
  annee_catechese?: AnneeCatecheseDto;
  classe_id?: string;
  classe?: ClasseDto;
  animateur_id?: string;
  animateur?: AnimateurDto;
  total_presences?: number;
  total_presents?: number;
  total_absents?: number;
  presences?: PresenceItemDto[];
  created_at?: string;
  updated_at?: string;
}

export interface CreateSeanceDto {
  annee_catechese_id?: string;
  classe_id: string;
  titre?: string;
  titre_lecon?: string;
  date_seance: string;
  heure_debut?: string;
  heure_fin?: string;
  duree_minutes?: number;
  description?: string;
  statut?: StatutSeance;
}

export interface UpdateSeanceDto extends Partial<CreateSeanceDto> {}

export interface UpdateSeanceStatutDto {
  statut: StatutSeance;
}

export type EvaluationType =
  | 'Interrogation'
  | 'Devoir'
  | 'Composition'
  | 'Examen'
  | 'Oral'
  | 'devoir'
  | 'interrogation'
  | 'examen'
  | 'comportement'
  | string;

export type EvaluationPeriode = 'Trimestre 1' | 'Trimestre 2' | 'Trimestre 3' | 'Annuelle' | string;
export type EvaluationStatus = 'Actif' | 'Inactif' | 'actif' | 'inactif';

export interface EvaluationStatsDto {
  moyenne_classe?: number;
  plus_forte_note?: number;
  plus_faible_note?: number;
  saisies_effectuees?: number;
  total_eleves?: number;
  saisies_ratio?: string;
}

export interface EvaluationDto {
  id: string;
  nom?: string;
  titre?: string;
  type?: EvaluationType;
  type_eval?: EvaluationType;
  type_eval_code?: string;
  periode?: EvaluationPeriode;
  date?: string;
  date_evaluation?: string;
  coefficient?: number;
  coefficient_label?: string;
  bareme?: number;
  note_max?: number;
  bareme_label?: string;
  statut?: EvaluationStatus;
  statut_code?: 'actif' | 'inactif';
  anneePastorale?: string;
  annee_catechese_id?: string;
  annee_catechese?: AnneeCatecheseDto;
  classe_id?: string;
  classe?: ClasseDto | any;
  section?: string;
  niveau?: string;
  module_trimestriel_id?: string;
  module_trimestriel?: ModuleTrimestrielDto;
  observation?: string;
  description?: string;
  stats?: EvaluationStatsDto;
  notes?: NoteDto[] | any[];
  created_at?: string;
  updated_at?: string;
}

export type EvaluationItem = EvaluationDto;

export interface CreateEvaluationDto {
  nom?: string;
  titre?: string;
  type?: EvaluationType;
  type_eval?: EvaluationType;
  type_eval_code?: string;
  periode?: EvaluationPeriode;
  date?: string;
  date_evaluation?: string;
  coefficient?: number;
  bareme?: number;
  note_max?: number;
  anneePastorale?: string;
  statut?: EvaluationStatus;
  observation?: string;
  section?: string;
  niveau?: string;
  classe?: string;
  classe_id?: string;
  annee_catechese_id?: string;
  module_trimestriel_id?: string;
}

export interface UpdateEvaluationDto extends Partial<CreateEvaluationDto> {}

export interface UpdateEvaluationStatutDto {
  statut?: EvaluationStatus | 'actif' | 'inactif';
  status?: EvaluationStatus | 'actif' | 'inactif';
}

export interface NoteDto {
  id: string;
  catechumene_id?: string;
  catechumeneId?: string;
  matricule?: string;
  code_catechumene?: string;
  nom?: string;
  prenoms?: string;
  nom_prenoms?: string;
  nomPrenoms?: string;
  note_obtenue?: number | null;
  note?: number | null;
  appreciation?: string | null;
  catechumene?: CatechumeneDto;
  created_at?: string;
}

export interface NoteItemGridDto {
  catechumene_id: string;
  code_catechumene: string;
  nom: string;
  prenoms: string;
  nom_prenoms: string;
  note_obtenue: number | null;
  appreciation: string | null;
  note_id?: string;
}

export interface CatechumeneNoteDto extends NoteDto {}

export interface RecordNotesBatchDto {
  notes: {
    catechumene_id: string;
    note_obtenue: number;
    appreciation?: string;
  }[];
}

export interface BatchSaveNotesDto {
  notes: CatechumeneNoteDto[];
}

export interface BulletinTrimestrielDto {
  id: string;
  moyenne_connaissances: number;
  moyenne_comportement: number;
  moyenne_generale: number;
  rang: number;
  appreciation_generale?: string;
  decision?: 'admis' | 'rattrapage' | 'redouble';
  catechumene?: CatechumeneDto;
  module_trimestriel?: ModuleTrimestrielDto;
}

export interface CalculerBulletinDto {
  classe_id: string;
  module_trimestriel_id: string;
}

export type DecisionStatus =
  | 'Admis'
  | 'Non admis'
  | 'Ajourné'
  | 'admis_niveau_superieur'
  | 'redoublement'
  | 'reorientation'
  | string;

export interface DecisionFinAnneeDto {
  id: string;
  decision_finale: 'admis_niveau_superieur' | 'redoublement' | 'reorientation' | string;
  observations?: string;
  catechumene?: CatechumeneDto;
}

export interface CreateDecisionFinAnneeDto {
  catechumene_id: string;
  annee_catechese_id?: string;
  decision_finale: 'admis_niveau_superieur' | 'redoublement' | 'reorientation' | string;
  observations?: string;
}

export interface BilanAnnuelItem {
  id?: string;
  catechumeneId: string;
  catechumene_id?: string;
  matricule: string;
  nomPrenoms: string;
  nom_prenoms?: string;
  section: string;
  niveau: string;
  classe: string;
  classe_id?: string;
  anneePastorale: string;
  annee_pastorale?: string;
  moyenneGenerale: number;
  moyenne_annuelle?: number;
  presenceCoursPct: number;
  presence_cours_pct?: number;
  presenceMesse: string;
  presence_messe?: string;
  presenceCEB: string;
  presence_ceb?: string;
  presenceMouvement: string;
  presence_mouvement?: string;
  decision: DecisionStatus;
  decision_code?: 'admis' | 'redouble' | 'exclu' | 'sacrement_valide';
  observations?: string;
}

export interface ValiderBilanDto {
  annee_pastorale: string;
  classe: string;
  valide?: boolean;
}

export interface SaveDecisionFinAnneeDto {
  catechumeneId?: string;
  catechumene_id?: string;
  inscription_annuelle_id?: string;
  moyenneGenerale?: number;
  moyenne_annuelle?: number;
  decision: DecisionStatus | 'admis' | 'redouble' | 'exclu' | 'sacrement_valide';
  presenceCoursPct?: number;
  presenceMesse?: string;
  presenceCEB?: string;
  presenceMouvement?: string;
  date_decision?: string;
}

// ====================================================================================
// 6. FINANCES & CAISSE (Conforme à 100% aux écrans du prototype Cathéo Admin)
// ====================================================================================

// SCREEN 1 — Opérations (Paiements en attente)
export interface OperationPaiementDto {
  id: string;
  uuid: string;
  reference: string; // OP-2026-0001
  libelle: string; // Inscription annuelle Catéchèse / Baptême
  montant: number;
  montant_paye: number;
  echeance?: string;
  statut: 'en_attente' | 'partiellement_paye' | 'paye' | 'annule';
  annee_catechese_id?: string;
  catechumene_id?: string;
  tarif_id?: string;
  catechumene?: {
    id: string;
    uuid: string;
    matricule: string;
    nom: string;
    prenoms: string;
    nom_complet: string;
  };
  tarif?: TarifDto;
  annee_catechese?: AnneeCatecheseDto;
  created_at?: string;
  updated_at?: string;
}

export interface CreateOperationPaiementDto {
  annee_catechese_id?: string;
  catechumene_id?: string;
  tarif_id?: string;
  libelle: string;
  montant: number;
  echeance?: string;
}

export interface PayerOperationDto {
  mode_paiement: 'especes' | 'mobile_money' | 'wave' | 'mtn' | 'orange' | 'moov' | 'cheque' | 'virement';
  reference_transaction?: string;
  date_paiement?: string;
  notes?: string;
}

export interface GenererOperationsParTarifDto {
  tarif_id: string;
}

// SCREEN 2 — Caisse (Paiements encaissés & KPIs Trésorerie)
export interface CaisseParoissialeDto {
  id: string;
  uuid?: string;
  reference_document?: string; // REC-2026-0041
  reference?: string; // REC-2026-0041
  date_mouvement: string;
  libelle: string;
  categorie?: string;
  montant: number;
  mode_paiement?: string;
  caissier_nom?: string;
  solde_apres?: number;
  type_mouvement: 'entree' | 'recette' | 'sortie' | 'depense' | 'remboursement';
  created_at?: string;
}

export interface CaisseKpiDto {
  solde_en_caisse: number;
  total_encaisse: number;
  total_rembourse: number;
  paiements_valides_count: number;
}

export interface RemboursementRequestDto {
  montant_rembourse?: number;
  motif: string;
}

// SCREEN 3 — Versements de caisse
export interface VersementDto {
  id: string;
  uuid?: string;
  reference: string; // VRS-2026-0001
  periode_concernee: string; // Juin 2026
  montant_verse: number;
  mode_remise: 'cheque' | 'especes' | 'virement' | string;
  effectue_par?: string;
  destinataire?: string;
  statut: 'valide' | 'en_attente' | 'annule';
  annee_catechese_id?: string;
  annee_libelle?: string;
  user?: UserDto;
  created_at?: string;
  updated_at?: string;
}

export interface CreateVersementDto {
  annee_catechese_id?: string;
  periode_concernee: string;
  montant_verse: number;
  mode_remise: 'cheque' | 'especes' | 'virement' | string;
  effectue_par?: string;
  destinataire?: string;
}

export interface UpdateVersementDto extends Partial<CreateVersementDto> {
  statut?: 'valide' | 'en_attente' | 'annule';
}

export interface VersementKpiDto {
  total_en_caisse: number;
  total_deja_verse: number;
  reste_a_reverser: number;
}

// Aliases
export type VersementCureDto = VersementDto;
export type CreateVersementCureDto = CreateVersementDto;
export type VersementCureKpiDto = VersementKpiDto;

// SCREEN 4 — Configuration des paiements (Tarifs par niveau)
export interface TarifDto {
  id: string;
  uuid: string;
  intitule: string;
  nom?: string;
  description?: string;
  montant: number;
  est_obligatoire: boolean;
  type_tarif: string;
  statut: 'actif' | 'inactif';
  periode_debut?: string;
  periode_fin?: string;
  annee_catechese_id?: string;
  anneeCatecheseId?: string;
  annee_libelle?: string;
  niveau_id?: string;
  niveauId?: string;
  niveau_nom?: string;
  niveau_ids?: string[];
  niveauxIds?: string[];
  annee_catechese?: AnneeCatecheseDto;
  niveau?: NiveauDto;
  niveaux?: NiveauDto[];
  created_at?: string;
  updated_at?: string;
}

export interface CreateTarifDto {
  annee_catechese_id?: string;
  niveau_id?: string;
  niveau_ids?: string[];
  intitule: string;
  description?: string;
  montant: number;
  periode_debut?: string;
  periode_fin?: string;
  est_obligatoire?: boolean;
  type_tarif: string;
  statut?: 'actif' | 'inactif';
}

export interface UpdateTarifDto extends Partial<CreateTarifDto> {}

// ==========================================
// 7. COMMUNICATION, NOTIFICATIONS & AUDIT
// ==========================================

export type CibleTypeAnnonce =
  | 'TOUS'
  | 'Tous'
  | 'ANIMATEURS'
  | 'Animateurs'
  | 'Catéchumènes'
  | 'SECTION'
  | 'Section'
  | 'NIVEAU'
  | 'Niveau'
  | 'CLASSE'
  | 'Classe'
  | string;

export type CanalCommunication = 'in_app' | 'app' | 'sms' | 'whatsapp' | 'email' | 'affichage' | 'tous' | string;
export type StatutAnnonce = 'brouillon' | 'programmee' | 'publiee' | 'envoyee' | 'archivee' | string;
export type PrioriteAnnonce = 'normale' | 'haute' | 'urgente' | string;

export interface AnnonceDto {
  id: string;
  titre: string;
  contenu: string;
  cible?: string;
  cible_type: CibleTypeAnnonce;
  cible_id?: string | null;
  cible_ids?: string[] | null;
  cible_nom?: string | null;
  canal: CanalCommunication;
  date_publication?: string;
  date_diffusion?: string;
  heure_diffusion?: string | null;
  date_expiration?: string | null;
  priorite?: PrioriteAnnonce;
  statut: StatutAnnonce;
  est_lu?: boolean;
  is_read?: boolean;
  annee_catechese?: AnneeCatecheseDto;
  section?: SectionDto;
  niveau?: NiveauDto;
  classe?: ClasseDto;
  ceb?: CebDto;
  mouvement?: MouvementDto;
  created_at?: string;
  updated_at?: string;
}

export interface CreateAnnonceDto {
  annee_catechese_id?: string;
  titre: string;
  contenu: string;
  cible?: string;
  cible_type?: CibleTypeAnnonce;
  cible_id?: string;
  cible_ids?: string[];
  cible_nom?: string;
  section_id?: string;
  niveau_id?: string;
  classe_id?: string;
  ceb_id?: string;
  mouvement_id?: string;
  canal?: CanalCommunication;
  date_publication?: string;
  date_diffusion?: string;
  heure_diffusion?: string;
  date_expiration?: string;
  priorite?: PrioriteAnnonce;
  statut?: StatutAnnonce;
}

export interface UpdateAnnonceDto extends Partial<CreateAnnonceDto> {}

export interface NotificationFeedItemDto extends AnnonceDto {}

export interface UnreadNotificationsCountDto {
  unread_count: number;
}

export interface NotificationLogDto {
  id: string;
  destinataire_contact: string;
  type_canal: string;
  message: string;
  statut_envoi: 'succes' | 'echec';
  date_envoi: string;
}

export interface AuditLogDto {
  id: string;
  action: string;
  description?: string;
  ip_address?: string;
  user?: UserDto;
  created_at: string;
}

// ==========================================
// 8. DASHBOARD & IMPRESSIONS & EXPORTS
// ==========================================

export interface DashboardSummaryDto {
  catechumenes_total: number;
  nouveaux_ce_mois: number;
  inscriptions_valides: number;
  preinscriptions_en_attente: number;
  animateurs_actifs: number;
  classes_ouvertes: number;
  recettes_du_mois: number;
  taux_presence_global: number;
  repartition_par_section: { section: string; total: number }[];
  evolution_recettes_mensuelles: { mois: string; montant: number }[];
  dernieres_preinscriptions: PreinscriptionDto[];
}

export interface PrintHeaderDto {
  diocese: string;
  doyenne: string;
  paroisse: string;
  adresse: string;
  telephone: string;
  email: string;
  logo_url: string | null;
  coordination: string;
  annee: string;
}

export interface PrintFilterRequestDto {
  annee_catechese_id?: string;
  section_id?: string;
  niveau_id?: string;
  classe_id?: string;
  catechumene_id?: string;
  sacrament?: string;
  debut_cours?: string;
  jour?: string;
  nombre_seances?: number;
}

export interface ExportRequestDto {
  format: 'xlsx' | 'pdf' | 'csv';
  section_id?: string;
  niveau_id?: string;
  classe_id?: string;
  date_debut?: string;
  date_fin?: string;
}

// =====================================================================
// 9. DOCUMENTS OFFICIELS (MODÈLES & GÉNÉRATION)
// =====================================================================

export type TypeDocumentOfficiel =
  | 'certificat'
  | 'attestation'
  | 'convocation'
  | 'carte'
  | 'fiche'
  | 'autre'
  | string;

export interface ModeleDocumentVariableDto {
  tag: string;
  description: string;
}

export interface ModeleDocumentDto {
  id: string;
  uuid: string;
  titre: string;
  code?: string;
  type_document: TypeDocumentOfficiel;
  description?: string;
  contenu: string;
  variables_disponibles: ModeleDocumentVariableDto[];
  signature_nom?: string;
  signature_titre?: string;
  statut: 'actif' | 'inactif';
  is_system: boolean;
  created_at?: string;
  updated_at?: string;
}

export interface CreateModeleDocumentDto {
  titre: string;
  code?: string;
  type_document: TypeDocumentOfficiel;
  description?: string;
  contenu: string;
  variables_disponibles?: ModeleDocumentVariableDto[];
  signature_nom?: string;
  signature_titre?: string;
  statut?: 'actif' | 'inactif';
}

export interface UpdateModeleDocumentDto extends Partial<CreateModeleDocumentDto> {}

export interface DocumentGenereDto {
  id: string;
  uuid: string;
  reference_document: string;
  reference?: string;
  titre: string;
  type_document: TypeDocumentOfficiel;
  contenu: string;
  metadonnees?: Record<string, any>;
  date_generation: string;
  statut: 'valide' | 'annule' | string;
  modele_document_id?: string;
  modele_titre?: string;
  catechumene_id?: string;
  catechumene?: {
    id: string;
    matricule: string;
    nom: string;
    prenom: string;
    nom_complet: string;
  };
  annee_catechese_id?: string;
  annee_libelle?: string;
  user?: {
    id: string;
    name: string;
    email: string;
  };
  created_at?: string;
  updated_at?: string;
}

export interface GenererDocumentDto {
  modele_document_id: string;
  catechumene_id: string;
  annee_catechese_id?: string;
  date_generation?: string;
  variables_personnalisees?: Record<string, string>;
}

export interface GenererDocumentsMasseDto {
  modele_document_id: string;
  classe_id?: string;
  niveau_id?: string;
  annee_catechese_id?: string;
  catechumenes_ids?: string[];
}

// =====================================================================
// 10. MODULE IMPRESSIONS & FICHES OFFICIELLES
// =====================================================================

export interface ImpressionEntetePayloadDto {
  diocese: string;
  doyenne: string;
  paroisse: string;
  nom_paroisse: string;
  nom: string;
  ville: string;
  commune: string;
  adresse: string;
  telephone: string;
  email: string;
  site_web?: string;
  cure_nom: string;
  coordination: string;
  coordination_nom: string;
  logo_url?: string | null;
  annee: string;
  annee_libelle: string;
  date_edition: string;
}

export interface ImpressionFiltresDto {
  annee_catechese_id?: string;
  section_id?: string;
  niveau_id?: string;
  classe_id?: string;
  catechumene_id?: string;
  sacrament?: string;
  debut_cours?: string;
  jour?: string;
  nombre_seances?: number;
}

export interface ImpressionDocumentMetaDto {
  titre: string;
  classe_nom?: string;
  section_nom?: string;
  niveau_nom?: string;
  annee_pastorale?: string;
  jour?: string;
  sacrament?: string;
  animateurs?: string[];
  dates_seances?: string[];
  total_eleves?: number;
  total_candidats?: number;
  total_fiches?: number;
  effectif_garcons?: number;
  effectif_filles?: number;
  total_baptises?: number;
  total_non_baptises?: number;
}

export interface ImpressionResponseDto<T = any> {
  status: 'success' | 'error';
  entete: ImpressionEntetePayloadDto;
  document: ImpressionDocumentMetaDto;
  colonnes?: string[] | Record<string, any>;
  lignes?: T[];
  fiches?: T[];
}

// =================================================================
// MODULE SACREMENTS (Baptême, 1ère Communion, Confirmation)
// =================================================================

export type SacrementCode = 'BAPTEME' | 'PREMIERE_COMMUNION' | 'CONFIRMATION';
export type SacrementStatut = 'non_recu' | 'preparation' | 'valide';

export interface SacrementDto {
  id: string;
  uuid: string;
  code: SacrementCode;
  nom: string;
  libelle: string;
  description?: string;
  ordre: number;
  statut: string;
}

export interface ParcoursSacrementItemDto {
  id?: string;
  sacrement_id: string;
  sacrement_code: SacrementCode;
  sacrement_nom: string;
  ordre: number;
  statut: SacrementStatut;
  date_sacrement?: string | null;
  lieu?: string | null;
  paroisse_nom?: string | null;
  celebrant?: string | null;
  numero_registre?: string | null;
  num_carnet?: string | null;
  observations?: string | null;
  annee_pastorale?: string | null;
  validated_at?: string | null;
  validated_by?: {
    id: string;
    name: string;
  } | null;
}

export interface CatechumeneSacrementListRowDto {
  id: string;
  uuid: string;
  matricule: string;
  code_catechumene: string;
  nom: string;
  prenom: string;
  prenoms: string;
  nom_complet: string;
  sexe: 'M' | 'F' | string;
  date_naissance?: string;
  telephone?: string;
  statut: string;
  section_id?: string;
  section_nom?: string;
  niveau_id?: string;
  niveau_nom?: string;
  classe_id?: string;
  classe_nom?: string;
  annee_pastorale?: string;
  sacrements_status: {
    bapteme: SacrementStatut;
    premiere_communion: SacrementStatut;
    confirmation: SacrementStatut;
  };
  est_baptise: boolean;
  date_bapteme?: string;
  date_premiere_communion?: string;
  date_confirmation?: string;
  created_at?: string;
}

export interface StoreCatechumenSacrementDto {
  sacrement_id: string; // UUID ou Code (ex: BAPTEME)
  annee_catechese_id?: string;
  statut?: 'preparation' | 'valide';
  date_sacrement?: string | null;
  lieu?: string | null;
  paroisse_nom?: string | null;
  celebrant?: string | null;
  numero_registre?: string | null;
  num_carnet?: string | null;
  observations?: string | null;
}

export interface UpdateCatechumenSacrementDto {
  statut?: 'preparation' | 'valide';
  date_sacrement?: string | null;
  lieu?: string | null;
  paroisse_nom?: string | null;
  celebrant?: string | null;
  numero_registre?: string | null;
  num_carnet?: string | null;
  observations?: string | null;
}

export interface SacrementFilterParams {
  section_id?: string;
  niveau_id?: string;
  classe_id?: string;
  sacrement_id?: string;
  statut?: 'preparation' | 'valide';
  annee_catechese_id?: string;
  search?: string;
  page?: number;
  per_page?: number;
}

